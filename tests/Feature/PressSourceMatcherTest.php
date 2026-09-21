<?php

namespace Tests\Feature;

use App\Enums\PressSourceMatchType;
use App\Models\PressSource;
use App\Services\PressSources\PressSourceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PressSourceMatcherTest extends TestCase
{
    use RefreshDatabase;

    private PressSourceMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = app(PressSourceMatcher::class);
    }

    public function test_exact_email_has_priority_over_higher_priority_domain(): void
    {
        $domain = PressSource::factory()->forDomain('agency.com')->create(['priority' => 100]);
        $exact = PressSource::factory()->create(['email' => 'news@agency.com', 'priority' => -10]);

        $this->assertTrue($this->matcher->match('news@agency.com')->is($exact));
        $this->assertFalse($this->matcher->match('news@agency.com')->is($domain));
    }

    public function test_only_active_sources_are_considered(): void
    {
        PressSource::factory()->inactive()->create(['email' => 'news@agency.com']);
        $activeDomain = PressSource::factory()->forDomain('agency.com')->create();

        $this->assertTrue($this->matcher->match('news@agency.com')->is($activeDomain));
    }

    public function test_email_matching_is_case_insensitive_and_trimmed(): void
    {
        $source = PressSource::factory()->create(['email' => 'NEWS@Agency.COM']);

        $this->assertSame('news@agency.com', $source->email);
        $this->assertTrue($this->matcher->match('  NeWs@AgEnCy.CoM  ')->is($source));
    }

    public function test_domain_matches_the_real_domain_not_a_substring_or_subdomain(): void
    {
        $source = PressSource::factory()->forDomain('agency.com')->create();

        $this->assertNull($this->matcher->match('news@fakeagency.com'));
        $this->assertNull($this->matcher->match('news@agency.com.evil.test'));
        $this->assertNull($this->matcher->match('news@sub.agency.com'));
        $this->assertTrue($this->matcher->match('news@agency.com')->is($source));
    }

    public function test_highest_priority_wins_between_rules_of_the_same_type(): void
    {
        PressSource::factory()->create(['email' => 'news@agency.com', 'priority' => 1]);
        $winner = PressSource::factory()->create(['email' => 'news@agency.com', 'priority' => 20]);

        $this->assertTrue($this->matcher->match('news@agency.com')->is($winner));
    }

    public function test_lowest_id_is_a_deterministic_tie_breaker(): void
    {
        $first = PressSource::factory()->forDomain('agency.com')->create(['priority' => 5]);
        PressSource::factory()->forDomain('agency.com')->create(['priority' => 5]);

        $this->assertTrue($this->matcher->match('news@agency.com')->is($first));
    }

    public function test_rules_only_match_their_configured_type(): void
    {
        PressSource::factory()->create([
            'match_type' => PressSourceMatchType::Domain,
            'email' => 'news@agency.com',
            'domain' => 'different.com',
        ]);

        $this->assertNull($this->matcher->match('news@agency.com'));
    }

    #[DataProvider('invalidEmails')]
    public function test_invalid_sender_addresses_do_not_match(string $email): void
    {
        PressSource::factory()->forDomain('agency.com')->create();
        $this->assertNull($this->matcher->match($email));
    }

    public static function invalidEmails(): array
    {
        return [[''], ['agency.com'], ['name@'], ['@agency.com'], ['name@@agency.com']];
    }

    public function test_returns_null_when_no_source_matches(): void
    {
        PressSource::factory()->forDomain('agency.com')->create();
        $this->assertNull($this->matcher->match('news@other.com'));
    }
}
