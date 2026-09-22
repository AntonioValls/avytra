<?php

namespace App\Enums;

/**
 * How buyers should reach the seller (docs/12-contact-system.md).
 */
enum ContactMethod: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Website = 'website';
    case ExternalForm = 'external_form';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => __('Email'),
            self::Phone => __('Phone'),
            self::Whatsapp => __('WhatsApp'),
            self::Website => __('Website'),
            self::ExternalForm => __('External form'),
            self::Other => __('Other'),
        };
    }

    /**
     * Label of the public button for this channel.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Email => __('Send email'),
            self::Phone => __('Call'),
            self::Whatsapp => __('WhatsApp'),
            self::Website => __('Go to the website'),
            self::ExternalForm => __('Fill in the form'),
            self::Other => __('See how to contact'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'envelope',
            self::Phone => 'phone',
            self::Whatsapp => 'chat-bubble-left-right',
            self::Website => 'globe-alt',
            self::ExternalForm => 'document-text',
            self::Other => 'information-circle',
        };
    }

    /**
     * The listing column that must be filled for this method to be usable.
     */
    public function channelColumn(): string
    {
        return match ($this) {
            self::Email => 'contact_email',
            self::Phone => 'contact_phone',
            self::Whatsapp => 'contact_whatsapp',
            self::Website => 'contact_website_url',
            self::ExternalForm => 'contact_form_url',
            self::Other => 'contact_other',
        };
    }
}
