<?php
declare(strict_types=1);

final class SimpleSectionsPdf
{
    private array $objects = [];

    public function download(
        string $filename,
        string $title,
        array $headers,
        array $rows
    ): never {
        $lines = [
            $title,
            str_repeat(
                '=',
                min(90, strlen($title))
            ),
            implode(' | ', $headers),
            str_repeat('-', 110),
        ];

        foreach ($rows as $row) {
            $lines[] = implode(
                ' | ',
                array_map(
                    static function ($value): string {
                        $normalized = preg_replace(
                            '/\s+/',
                            ' ',
                            (string)$value
                        ) ?: '';

                        return mb_substr(
                            $normalized,
                            0,
                            32
                        );
                    },
                    $row
                )
            );
        }

        $pages = array_chunk($lines, 44);

        $pageIds = [];
        $fontId = 3;
        $nextId = 4;

        foreach ($pages as $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;

            $pageIds[] = $pageId;

            $stream = "BT\n/F1 8 Tf\n36 806 Td\n";

            foreach ($pageLines as $index => $line) {
                if ($index > 0) {
                    $stream .= "0 -16 Td\n";
                }

                $escaped = str_replace(
                    ['\\', '(', ')'],
                    ['\\\\', '\\(', '\\)'],
                    $this->latin1($line)
                );

                $stream .= "({$escaped}) Tj\n";
            }

            $stream .= 'ET';

            $this->objects[$contentId] =
                '<< /Length '
                . strlen($stream)
                . " >>\nstream\n"
                . $stream
                . "\nendstream";

            $this->objects[$pageId] =
                "<< /Type /Page /Parent 2 0 R "
                . "/MediaBox [0 0 595 842] "
                . "/Resources << /Font << /F1 "
                . "{$fontId} 0 R >> >> "
                . "/Contents {$contentId} 0 R >>";
        }

        $kids = implode(
            ' ',
            array_map(
                static fn(int $id): string =>
                    "{$id} 0 R",
                $pageIds
            )
        );

        $this->objects[1] =
            '<< /Type /Catalog /Pages 2 0 R >>';

        $this->objects[2] =
            "<< /Type /Pages /Kids [{$kids}] "
            . '/Count '
            . count($pageIds)
            . ' >>';

        $this->objects[3] =
            '<< /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica >>';

        ksort($this->objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($this->objects as $id => $object) {
            $offsets[$id] = strlen($pdf);

            $pdf .= "{$id} 0 obj\n"
                . "{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxId = max(array_keys($this->objects));

        $pdf .= "xref\n0 "
            . ($maxId + 1)
            . "\n";

        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf(
                "%010d 00000 n \n",
                $offsets[$id] ?? 0
            );
        }

        $pdf .= "trailer\n<< /Size "
            . ($maxId + 1)
            . " /Root 1 0 R >>\n"
            . "startxref\n{$xref}\n%%EOF";

        header('Content-Type: application/pdf');

        header(
            'Content-Disposition: attachment; filename="'
            . preg_replace(
                '/[^A-Za-z0-9._-]/',
                '_',
                $filename
            )
            . '"'
        );

        header(
            'Content-Length: '
            . strlen($pdf)
        );

        echo $pdf;
        exit;
    }

    private function latin1(string $value): string
    {
        $converted = iconv(
            'UTF-8',
            'Windows-1252//TRANSLIT',
            $value
        );

        return $converted !== false
            ? $converted
            : $value;
    }
}
