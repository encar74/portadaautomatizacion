<?php

namespace App\Enums;

enum PressReleaseStatus: string
{
    case Received = 'received';
    case UnmatchedSender = 'unmatched_sender';
    case Queued = 'queued';
    case Processing = 'processing';
    case Repairing = 'repairing';
    case Processed = 'processed';
    case NeedsReview = 'needs_review';
    case AwaitingWordPressApproval = 'awaiting_wordpress_approval';
    case WordPressDraftCreated = 'wordpress_draft_created';
    case Error = 'error';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibido',
            self::UnmatchedSender => 'Remitente sin identificar',
            self::Queued => 'En cola',
            self::Processing => 'Procesando',
            self::Repairing => 'Reparando artículo',
            self::Processed => 'Procesado',
            self::NeedsReview => 'Pendiente de revisión',
            self::AwaitingWordPressApproval => 'Pendiente de envío a WordPress',
            self::WordPressDraftCreated => 'Borrador en WordPress',
            self::Error => 'Error',
            self::Ignored => 'Ignorado',
        };
    }
}
