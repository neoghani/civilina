<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Symfony\Component\Process\Process;

class NormativeOcrToolchain
{
    /**
     * @return array{
     *   ready:bool,tesseract:string,pdftoppm:string,tessdata_dir:?string,
     *   tesseract_version:?string,pdftoppm_version:?string,
     *   languages:array<int,string>,selected_language:?string
     * }
     */
    public function inspect(): array
    {
        $tesseract = (string) config('civilina_normative_ocr.tesseract', 'tesseract');
        $pdftoppm = (string) config('civilina_normative_ocr.pdftoppm', 'pdftoppm');
        $tessdataDir = $this->tessdataDir();

        $tesseractOk = $this->works([$tesseract, '--version']);
        $pdftoppmOk = $this->works([$pdftoppm, '-v']);

        $languages = $tesseractOk
            ? $this->languages($tesseract, $tessdataDir)
            : [];

        return [
            'ready'=>$tesseractOk && $pdftoppmOk && ! empty($languages),
            'tesseract'=>$tesseract,
            'pdftoppm'=>$pdftoppm,
            'tessdata_dir'=>$tessdataDir,
            'tesseract_version'=>$tesseractOk
                ? $this->firstLine([$tesseract, '--version'])
                : null,
            'pdftoppm_version'=>$pdftoppmOk
                ? $this->firstLine([$pdftoppm, '-v'], true)
                : null,
            'languages'=>$languages,
            'selected_language'=>$this->selectLanguage($languages),
        ];
    }

    public function selectLanguage(array $languages): ?string
    {
        $languages = array_values(array_unique(array_map('trim', $languages)));

        foreach ((array) config('civilina_normative_ocr.preferred_languages', ['fra+eng','fra','eng']) as $preferred) {
            $parts = explode('+', $preferred);
            $ok = true;

            foreach ($parts as $part) {
                if (! in_array($part, $languages, true)) {
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                return $preferred;
            }
        }

        return $languages[0] ?? null;
    }

    private function tessdataDir(): ?string
    {
        $dir = trim((string) config('civilina_normative_ocr.tessdata_dir', ''));

        return $dir !== '' ? $dir : null;
    }

    /**
     * @return array<int,string>
     */
    private function languages(string $tesseract, ?string $tessdataDir): array
    {
        try {
            $command = [$tesseract, '--list-langs'];

            if ($tessdataDir) {
                $command[] = '--tessdata-dir';
                $command[] = $tessdataDir;
            }

            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            $output = trim($process->getOutput()."\n".$process->getErrorOutput());
            $lines = preg_split('/\R+/', $output) ?: [];

            $languages = [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || str_contains(strtolower($line), 'list of available languages')) {
                    continue;
                }

                if (preg_match('/^[a-zA-Z_]{2,20}$/', $line)) {
                    $languages[] = $line;
                }
            }

            return array_values(array_unique($languages));
        } catch (\Throwable) {
            return [];
        }
    }

    private function works(array $command): bool
    {
        try {
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            return $process->isSuccessful()
                || trim($process->getOutput().$process->getErrorOutput()) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    private function firstLine(array $command, bool $stderr = false): ?string
    {
        try {
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            $output = trim(
                ($stderr ? $process->getErrorOutput() : $process->getOutput())
                ."\n".
                ($stderr ? $process->getOutput() : $process->getErrorOutput())
            );

            $line = preg_split('/\R+/', $output)[0] ?? null;

            return $line ? trim($line) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
