<?php

namespace Splintr\Wp\Plugin\SplintrCheckout\Services;

class ViewService
{
    protected $basePath;
    protected $baseUrl;

    /**
     * @param string $basePath
     * @param string $baseUrl
     */
    public function __construct($basePath, $baseUrl)
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param $viewFilePath
     * @param array $params
     *
     * @return string|void|null
     */
    public function render($viewFilePath, $params = [])
    {
        global $wp_query;

        $extension = '.php';
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        $wp_query->query_vars['viewParams'] = $params;
        if (strpos($viewFilePath, '/') === 1) {
            return load_template($viewFilePath . $extension, false);

            // phpcs:ignore PSR2.ControlStructures.ControlStructureSpacing.SpacingAfterOpenBrace
        } elseif (!empty($templateContent = locate_template($viewFilePath . $extension, true, false))) {
            return $templateContent;
        } elseif (file_exists($this->basePath . DIRECTORY_SEPARATOR . $viewFilePath . $extension)) {
            return load_template($this->basePath . DIRECTORY_SEPARATOR . $viewFilePath . $extension, false);
        }

        $errorMessage = sprintf(
            "View file not working: %s.\nTrace: %s",
            $viewFilePath . $extension,
            print_r(debug_backtrace(), true)
        );
        // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
        trigger_error($errorMessage, E_USER_WARNING);

        return null;
    }
}
