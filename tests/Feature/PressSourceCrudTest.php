<?php

namespace Tests\Feature;

use App\Enums\PressSourceMatchType;
use App\Enums\ProcessingMode;
use App\Models\PressSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PressSourceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_index_is_available(): void
    {
        $this->get(route('press-sources.index'))->assertOk()->assertSee('Fuentes de prensa');
    }

    public function test_source_can_be_created_with_normalized_data(): void
    {
        $response = $this->post(route('press-sources.store'), [
            'name' => 'Agencia', 'match_type' => 'exact_email', 'email' => 'NEWS@AGENCY.COM',
            'domain' => 'ignored.com', 'is_active' => '1', 'processing_mode' => 'automatic',
            'default_category' => 'Empresas', 'default_tags' => 'uno, dos, uno',
            'priority' => '10', 'notes' => 'Fiable',
        ]);

        $response->assertRedirect(route('press-sources.index'));
        $source = PressSource::firstOrFail();
        $this->assertSame('news@agency.com', $source->email);
        $this->assertNull($source->domain);
        $this->assertSame(['uno', 'dos'], $source->default_tags);
    }

    public function test_rule_specific_value_is_required(): void
    {
        $this->post(route('press-sources.store'), [
            'name' => 'Agencia', 'match_type' => 'domain', 'is_active' => '1',
            'processing_mode' => 'review', 'priority' => '0',
        ])->assertSessionHasErrors('domain');
    }

    public function test_source_can_be_updated_and_deleted(): void
    {
        $source = PressSource::factory()->create();
        $this->put(route('press-sources.update', $source), [
            'name' => 'Editada', 'match_type' => 'domain', 'domain' => 'agency.com',
            'is_active' => '0', 'processing_mode' => 'ignore', 'priority' => '5',
        ])->assertRedirect(route('press-sources.index'));

        $source->refresh();
        $this->assertSame('Editada', $source->name);
        $this->assertSame(PressSourceMatchType::Domain, $source->match_type);
        $this->assertSame(ProcessingMode::Ignore, $source->processing_mode);
        $this->assertFalse($source->is_active);

        $this->delete(route('press-sources.destroy', $source))->assertRedirect(route('press-sources.index'));
        $this->assertDatabaseMissing('press_sources', ['id' => $source->id]);
    }
}
