<?php

namespace App\Enums;

enum PressReleaseStatus: string
{
    case Received = 'received';
    case UnmatchedSender = 'unmatched_sender';
    case Queued = 'queued';
    case Processing = 'processing';
    case Processed = 'processed';
    case NeedsReview = 'needs_review';
    case WordPressDraftCreated = 'wordpress_draft_created';
    case Error = 'error';
    case Ignored = 'ignored';
}
