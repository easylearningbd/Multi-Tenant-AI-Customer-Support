<?php

namespace App\Enums;

enum KnowledgeSourceType: string
{
    case TEXT = 'text';
    case TXT = 'txt';
    case MARKDOWN = 'markdown';
    case CSV = 'csv';
    case PDF = 'pdf';
    case DOCX = 'docx';
    case WEBSITE = 'website';
    case SITEMAP = 'sitemap';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('Text'),
            self::TXT => __('TXT'),
            self::MARKDOWN => __('Markdown'),
            self::CSV => __('CSV'),
            self::PDF => __('PDF'),
            self::DOCX => __('DOCX'),
            self::WEBSITE => __('Website'),
            self::SITEMAP => __('Sitemap'),
        };
    }
}
