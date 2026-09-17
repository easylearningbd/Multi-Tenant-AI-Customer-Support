<?php

namespace App\Services;

use App\Enums\KnowledgeSourceType;
use App\Exceptions\KnowledgeExtractionException;
use App\Models\KnowledgeSource;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use ZipArchive as NativeZipArchive;

final class SourceTextExtractor
{
    public function __construct(private readonly WebsiteCrawler $crawler) {}

    public function extract(KnowledgeSource $source): string
    {
        return match ($source->type) {
            KnowledgeSourceType::TEXT => (string) $source->raw_text,
            KnowledgeSourceType::TXT, KnowledgeSourceType::MARKDOWN, KnowledgeSourceType::CSV => $this->readPrivateFile($source),
            KnowledgeSourceType::PDF => $this->extractPdf($source),
            KnowledgeSourceType::DOCX => $this->extractDocx($source),
            KnowledgeSourceType::WEBSITE => $this->crawler->crawl((string) $source->source_url, false, $source->page_limit ?? 1),
            KnowledgeSourceType::SITEMAP => $this->crawler->crawl((string) $source->sitemap_url, true, $source->page_limit ?? 10),
        };
    }

    private function readPrivateFile(KnowledgeSource $source): string
    {
        if (! $source->file_path || ! Storage::disk(config('neuraldesk.storage.knowledge_disk'))->exists($source->file_path)) {
            throw new KnowledgeExtractionException('The uploaded source file is no longer available.');
        }

        return Storage::disk(config('neuraldesk.storage.knowledge_disk'))->get($source->file_path);
    }

    private function extractPdf(KnowledgeSource $source): string
    {
        try {
            return (new Parser)->parseContent($this->readPrivateFile($source))->getText();
        } catch (\Throwable $exception) {
            throw new KnowledgeExtractionException('The PDF could not be read.', previous: $exception);
        }
    }

    private function extractDocx(KnowledgeSource $source): string
    {
        $disk = Storage::disk(config('neuraldesk.storage.knowledge_disk'));
        if (! $source->file_path || ! $disk->exists($source->file_path)) {
            throw new KnowledgeExtractionException('The uploaded source file is no longer available.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'neuraldesk-docx-');
        if ($temporary === false || file_put_contents($temporary, $disk->get($source->file_path)) === false) {
            throw new KnowledgeExtractionException('The DOCX file could not be prepared safely.');
        }
        $zip = new NativeZipArchive;
        if ($zip->open($temporary) !== true) {
            @unlink($temporary);
            throw new KnowledgeExtractionException('The DOCX file could not be opened.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($temporary);
        if (! is_string($xml)) {
            throw new KnowledgeExtractionException('The DOCX file did not contain a readable document.');
        }
        $xml = preg_replace('/<w:tab\/?[^>]*>/', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
