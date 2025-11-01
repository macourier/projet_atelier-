<?php
declare(strict_types=1);

namespace App\Service;

use Twig\Environment as TwigEnvironment;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    private TwigEnvironment $twig;
    private Options $options;

    public function __construct(TwigEnvironment $twig, ?array $options = null)
    {
        $this->twig = $twig;
        $this->options = new Options();
        $this->options->set('isRemoteEnabled', true);
        $this->options->set('defaultFont', 'DejaVu Sans');

        if ($options) {
            foreach ($options as $k => $v) {
                $this->options->set($k, $v);
            }
        }
    }

    /**
     * Render a Twig template to PDF binary string.
     *
     * @param string $template Twig template path (e.g. 'pdf/facture.twig')
     * @param array $context
     * @param array|null $dompdfOptions extra options for Dompdf constructor
     * @return string PDF binary content
     */
    public function renderPdf(string $template, array $context = [], ?array $dompdfOptions = null): string
    {
        $html = $this->twig->render($template, $context);

        $dompdf = new Dompdf($this->options);
        if (is_array($dompdfOptions)) {
            // apply options if needed (paper, orientation...)
            if (!empty($dompdfOptions['paper'])) {
                $paper = $dompdfOptions['paper'];
                $orientation = $dompdfOptions['orientation'] ?? 'portrait';
                $dompdf->setPaper($paper, $orientation);
            }
        }

        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Save rendered PDF to disk and return the path.
     *
     * @param string $template
     * @param array $context
     * @param string $path
     * @param array|null $dompdfOptions
     * @return string saved file path
     */
    public function savePdf(string $template, array $context, string $path, ?array $dompdfOptions = null): string
    {
        $pdfData = $this->renderPdf($template, $context, $dompdfOptions);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($path, $pdfData);
        return $path;
    }
}
