<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

/**
 * Renders the BDD story blocks (title bar, steps, data sets, failures)
 * for finished scenarios. This is a pure renderer: it no longer extends
 * Module or hooks PHPUnit result printers — the event-driven BDDExtension
 * feeds it. The visual format matches the original 2014 output.
 */
class BDDPrinter
{
    /* General */
    protected int $initialSpace = 0;
    protected string $defaultSpaceChar = ' ';
    protected string $defaultStatusColor = 'dark_gray';
    protected int $defaultSpacing = 2;

    /* Title Bar */
    protected string $defaultTitleBarChar = '*';
    protected int $minimumTitleBarLength = 50;
    protected int $defaultTitleBarTopRows = 1;
    protected int $defaultTitleBarBotRows = 1;
    protected string $defaultTitleBarLeftStars = '2';
    protected string $defaultTitleBarRightStars = '2';
    protected string $defaultTitleBarLeftSpace = '1';
    protected string $defaultTitleBarRightSpace = '3';

    public bool $enableColors = true;

    /** @var resource */
    private $out;

    /** @param resource|null $out */
    public function __construct(string $colors = 'auto', $out = null)
    {
        $this->enableColors = match ($colors) {
            'never' => false,
            'always' => true,
            default => function_exists('posix_isatty') ? @posix_isatty(STDOUT) : false,
        };
        $this->out = $out ?? STDOUT;
    }

    /**
     * Render one finished scenario.
     *
     * @param array<string, mixed> $details
     */
    public function render(array $details): void
    {
        $offset = $this->initialSpace;
        $spacing = $this->defaultSpacing;

        // Data-driven scenarios are shown as "(Multi)" in the title bar,
        // with per-row statuses/failures in the data set block (2014 parity).
        $displayStatus = isset($details['multi']) ? 'Multi' : (string) $details['status'];
        $this->displayStoryTitleBar($offset, (string) ($details['title'] ?? ''), $displayStatus);
        $offset += $spacing;

        $this->displayStoryDetails($offset, $this->normalizeSteps($details));

        if (isset($details['multi'])) {
            $this->displayStoryDataSet($offset, $this->normalizeDataSet($details));
        }

        $this->displayScenarioDetails($offset, $details);

        $this->write("\n\n");
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return list<array{step: string, status: string, exception?: string}>
     */
    protected function normalizeSteps(array $details): array
    {
        $steps = [];
        foreach (($details['steps'] ?? []) as $step) {
            $stepDetails = [];
            $stepDetails['status'] = $step['status']['result'] ?? 'Unknown';
            $stepDetails['step'] = $step['step'];
            if (isset($step['status']['exception']) && $step['status']['exception'] instanceof \Exception) {
                $stepDetails['exception'] = $step['status']['exception']->getMessage();
            }
            $steps[] = $stepDetails;
        }
        return $steps;
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array{headers: list<string>, depth: int, entries: array<string, list<array{value: string, status: string}>>, failures: list<array{entry: int, errorClass: string, msg: string, type: string}>}
     */
    protected function normalizeDataSet(array $details): array
    {
        $examples = $details['examples'];
        $headers = array_keys($examples);
        $entries = [];
        foreach ($headers as $header) {
            $entries[$header] = [];
        }
        $depth = count($examples[$headers[0]]);

        for ($i = 0; $i < $depth; $i++) {
            foreach ($headers as $header) {
                $entries[$header][$i] = [
                    'value' => $details['examples'][$header][$i],
                    'status' => $details['multi-steps']['status'][$i][$header] ?? 'Passed',
                ];
            }
        }

        $failures = [];
        if (!empty($details['multi-steps']['exception'])) {
            for ($i = 0; $i < $depth; $i++) {
                if (isset($details['multi-steps']['exception'][$i])
                    && $details['multi-steps']['exception'][$i] instanceof \Exception
                ) {
                    $exception = $details['multi-steps']['exception'][$i];
                    $errorClass = get_class($exception);
                    $failures[] = [
                        'entry' => $i,
                        'errorClass' => $errorClass,
                        'msg' => $exception->getMessage(),
                        'type' => str_contains($errorClass, 'Incomplete') ? 'Incomplete' : 'Failed',
                    ];
                }
            }
        }

        return [
            'headers' => $headers,
            'depth' => $depth,
            'entries' => $entries,
            'failures' => $failures,
        ];
    }

    protected function getStoryStatus(array $details): string
    {
        return (string) $details['status'];
    }

    protected function getStepStatusPrefix(string $status): string
    {
        return match ($status) {
            'Passed' => '[+]',
            'Failed' => '[-]',
            'Pending' => '[P]',
            'Skipped' => '[S]',
            'Multi' => '[M]',
            'Abort' => '[A]',
            default => '[?]',
        };
    }

    protected function getStepStatusColor(string $status): string
    {
        return match ($status) {
            'Passed' => 'light_green',
            'Failed' => 'light_red',
            'Pending' => 'yellow',
            'Skipped' => 'brown',
            'Multi' => 'light_blue',
            default => 'light_gray',
        };
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'Passed' => 'green',
            'Failed' => 'red',
            'Incomplete', 'Pending' => 'yellow',
            'Skipped' => 'brown',
            'Multi' => 'light_blue',
            default => $this->defaultStatusColor,
        };
    }

    protected function displayStoryTitleBar(int $offset, string $title, string $status): void
    {
        $status = trim($status);
        $title = trim($title);

        $sp = $this->defaultSpaceChar;
        $bar = $this->defaultTitleBarChar;
        $lst = (int) $this->defaultTitleBarLeftStars;
        $rst = (int) $this->defaultTitleBarRightStars;
        $ls = (int) $this->defaultTitleBarLeftSpace;
        $rs = (int) $this->defaultTitleBarRightSpace;

        $prefix = 'Story: ';
        $statusColor = $this->getStatusColor($status);
        $barColor = 'light_gray';
        $displayStatus = " ($status)";
        $displayStatusColor = $this->highlight($displayStatus, $statusColor);
        $titleColor = $this->highlight($title, 'white');

        $colorAdjustment = strlen($displayStatusColor) - strlen($displayStatus) + strlen($titleColor) - strlen($title);

        $titleBarLn = max(
            $this->minimumTitleBarLength,
            $lst + $ls + strlen($prefix) + strlen($title) + strlen($displayStatus) + $rs + $rst
        );
        $centerLength = $titleBarLn - ($lst + $ls + strlen($prefix) + $rs + $rst);
        $centerLength += $colorAdjustment;

        for ($i = 0; $i < $this->defaultTitleBarTopRows; $i++) {
            $this->write(str_repeat($sp, $offset) . $this->highlight(str_repeat($bar, $titleBarLn), $barColor) . "\n");
        }

        $this->write(
            str_repeat($sp, $offset)
            . $this->highlight(str_repeat($bar, $lst), $barColor)
            . str_repeat($sp, $ls)
            . $prefix
        );

        $this->write($this->alignLeft($titleColor . $displayStatusColor, $centerLength));

        $this->write(
            str_repeat($sp, $rs)
            . $this->highlight(str_repeat($bar, $rst), $barColor)
            . "\n"
        );

        for ($i = 0; $i < $this->defaultTitleBarBotRows; $i++) {
            $this->write(str_repeat($sp, $offset) . $this->highlight(str_repeat($bar, $titleBarLn), $barColor) . "\n");
        }
    }

    /**
     * @param list<array{step: string, status: string, exception?: string}> $steps
     */
    protected function displayStoryDetails(int $offset, array $steps): void
    {
        $sp = $this->defaultSpaceChar;
        $this->write("\n");
        $this->write(str_repeat($sp, $offset) . "Steps:\n");
        $offset += $this->defaultSpacing;
        foreach ($steps as $step) {
            $status = $step['status'];
            $statusColor = $this->getStepStatusColor($status);
            $statusSymbol = $this->getStepStatusPrefix($status);

            $stepTxt = $this->highlight($statusSymbol . ' ' . $step['step'], $statusColor);
            $this->write(str_repeat($sp, $offset) . $stepTxt . "\n");

            if (isset($step['exception'])) {
                $this->write(
                    str_repeat($sp, $offset + $this->defaultSpacing)
                    . $status . ': ' . $this->highlight($step['exception'], $this->getStatusColor($status))
                    . "\n"
                );
            }
        }
    }

    /**
     * @param array{headers: list<string>, depth: int, entries: array<string, list<array{value: string, status: string}>>, failures: list<array{entry: int, errorClass: string, msg: string, type: string}>} $dataset
     */
    protected function displayStoryDataSet(int $offset, array $dataset): void
    {
        $padding = ' ';
        $sp = $this->defaultSpaceChar;
        $this->write("\n");
        $this->write(str_repeat($sp, $offset) . "DataSets:\n");
        $offset += $this->defaultSpacing;

        $headerLen = [];
        $contentLen = [];
        foreach ($dataset['headers'] as $header) {
            $headerLen[$header] = strlen($header);
            $contentLen[$header] = 0;
            foreach ($dataset['entries'][$header] as $entry) {
                $headerLen[$header] = max($headerLen[$header], strlen((string) $entry['value']) + strlen($padding));
                $contentLen[$header] = max($contentLen[$header], strlen((string) $entry['value']) + strlen($padding));
            }
        }

        $leftPadding = strlen((string) $dataset['depth']);

        $this->write(str_repeat($sp, $offset) . str_repeat($sp, $leftPadding) . '|');
        foreach ($dataset['headers'] as $header) {
            $displayHeader = (string) $header;
            $colorizedHeader = $this->highlight($displayHeader, 'light_blue');
            $adj = strlen($colorizedHeader) - strlen($displayHeader);
            $w = $headerLen[$header] + $adj;
            $this->write($this->alignCenter($colorizedHeader, $w));
            $this->write('|');
        }
        $this->write("\n");

        for ($i = 0; $i < $dataset['depth']; $i++) {
            $this->write(str_repeat($sp, $offset) . $this->alignRight((string) $i, $leftPadding) . '|');
            foreach ($dataset['headers'] as $header) {
                $field = $dataset['entries'][$header][$i]['value'] . $padding;
                $fieldStatus = $dataset['entries'][$header][$i]['status'];
                $fieldColor = $this->getStepStatusColor($fieldStatus);
                $colorizedField = $this->highlight($field, $fieldColor);

                $adj = strlen($colorizedField) - strlen($field);
                $digitLen = $contentLen[$header] + $adj;
                $columnLen = $headerLen[$header] + $adj;
                $this->write($this->alignCenter($this->alignRight($colorizedField, $digitLen), $columnLen));
                $this->write('|');
            }
            $this->write("\n");
        }

        $offset -= $this->defaultSpacing;

        if (!empty($dataset['failures'])) {
            $this->write("\n");
            $this->write(str_repeat($sp, $offset) . "Failures:\n");
            $offset += $this->defaultSpacing;
            foreach ($dataset['failures'] as $failure) {
                $failureColor = $this->getStatusColor($failure['type']);
                $failureMsg = $failure['type'] . ' - ' . $failure['msg'];
                $colorizedMsg = $this->highlight($failureMsg, $failureColor);
                $this->write(
                    str_repeat($sp, $offset) . 'DataSet(' . $this->alignLeft((string) $failure['entry'], $leftPadding) . "): $colorizedMsg \n"
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $details
     */
    protected function displayScenarioDetails(int $offset, array $details): void
    {
        $sp = $this->defaultSpaceChar;
        $this->write("\n");
        $this->write(str_repeat($sp, $offset) . "Scenario Details:\n");
        $offset += $this->defaultSpacing;
        $this->write(str_repeat($sp, $offset) . 'Total Asserts:' . ($details['numAsserts'] ?? 0) . "\n");
    }

    protected function alignLeft(string $text, int $width): string
    {
        $padding = $width - strlen($text);
        return $text . str_repeat(' ', max(0, $padding));
    }

    protected function alignRight(string $text, int $width): string
    {
        $padding = $width - strlen($text);
        return str_repeat(' ', max(0, $padding)) . $text;
    }

    protected function alignCenter(string $text, int $width): string
    {
        $l = strlen($text);
        $left = (int) ceil(($width - $l) / 2);
        $right = (int) floor(($width - $l) / 2);

        if ($left < 0) {
            $right += $left;
            $left = 0;
        }
        if ($right < 0) {
            $right = 0;
        }
        return str_repeat(' ', $left) . $text . str_repeat(' ', $right);
    }

    public function highlight(string $text, string $color, ?string $bgcolor = null): string
    {
        if ($this->enableColors) {
            if (isset($color)) {
                $text = $this->highlight_fg($text, $color);
            }
            if (isset($bgcolor)) {
                $text = $this->highlight_bg($text, $bgcolor);
            }
        }
        return $text;
    }

    public function highlight_bg(string $text, string $color): string
    {
        static $colorMap = [
            'black' => "\033[40m",
            'red' => "\033[41m",
            'green' => "\033[42m",
            'yellow' => "\033[43m",
            'blue' => "\033[44m",
            'magenta' => "\033[45m",
            'cyan' => "\033[46m",
            'light_gray' => "\033[47m",
        ];
        return ($colorMap[$color] ?? '') . $text . "\033[0m";
    }

    public function highlight_fg(string $text, string $color): string
    {
        static $colorMap = [
            'black' => "\033[0;30m",
            'dark_gray' => "\033[1;30m",
            'blue' => "\033[0;34m",
            'light_blue' => "\033[1;34m",
            'green' => "\033[0;32m",
            'light_green' => "\033[1;32m",
            'cyan' => "\033[0;36m",
            'light_cyan' => "\033[1;36m",
            'red' => "\033[0;31m",
            'light_red' => "\033[1;31m",
            'purple' => "\033[0;35m",
            'light_purple' => "\033[1;35m",
            'brown' => "\033[0;33m",
            'yellow' => "\033[1;33m",
            'light_gray' => "\033[0;37m",
            'white' => "\033[1;37m",
        ];
        return ($colorMap[$color] ?? '') . $text . "\033[0m";
    }

    protected function write(string $text): void
    {
        @fwrite($this->out, $text);
    }
}
