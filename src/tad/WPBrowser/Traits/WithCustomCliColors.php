<?php
/**
 * ${CARET}
 *
 * @since   TBD
 *
 * @package tad\WPBrowser\Traits
 */


namespace tad\WPBrowser\Traits;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

trait WithCustomCliColors
{
// foreground
//'black' => ['set' => 30, 'unset' => 39],
//'red' => ['set' => 31, 'unset' => 39],
//'green' => ['set' => 32, 'unset' => 39],
//'yellow' => ['set' => 33, 'unset' => 39],
//'blue' => ['set' => 34, 'unset' => 39],
//'magenta' => ['set' => 35, 'unset' => 39],
//'cyan' => ['set' => 36, 'unset' => 39],
//'white' => ['set' => 37, 'unset' => 39],
//'default' => ['set' => 39, 'unset' => 39],

// Background
//'black' => ['set' => 40, 'unset' => 49],
//'red' => ['set' => 41, 'unset' => 49],
//'green' => ['set' => 42, 'unset' => 49],
//'yellow' => ['set' => 43, 'unset' => 49],
//'blue' => ['set' => 44, 'unset' => 49],
//'magenta' => ['set' => 45, 'unset' => 49],
//'cyan' => ['set' => 46, 'unset' => 49],
//'white' => ['set' => 47, 'unset' => 49],
//'default' => ['set' => 49, 'unset' => 49],

// Options
//'bold' => ['set' => 1, 'unset' => 22],
//'underscore' => ['set' => 4, 'unset' => 24],
//'blink' => ['set' => 5, 'unset' => 25],
//'reverse' => ['set' => 7, 'unset' => 27],
//'conceal' => ['set' => 8, 'unset' => 28],

    protected static $colorSchemes = [
        'cold' => [
            'warning' => ['default', 'magenta', 'bold'],
            'info' => ['default', 'cyan'],
            'focus' => ['default', 'blue', 'bold'],
            'ok' => ['default', 'green'],
            'error' => ['default', 'red', 'bold'],
            'fail' => ['default', 'red'],
            'pending' => ['default', 'cyan'],
            'debug' => ['default', 'cyan'],
            'comment' => ['default', 'white'],
        ]
    ];

    protected function customizeOutputColors(OutputInterface $output, $colorScheme)
    {
        if (!array_key_exists($colorScheme, static::$colorSchemes)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Color scheme "%s" was not found.',
                    $colorScheme
                )
            );
        }

        $colorScheme = static::$colorSchemes[$colorScheme];

        $formatter = $output->getFormatter();

        foreach ($colorScheme as $style => list($background, $foreground)) {
            if (!$formatter->hasStyle($style)) {
                $options = isset($colorScheme[$style][2]) ? $colorScheme[$style][2] : [];
                $formatter->setStyle($style, new OutputFormatterStyle($foreground, $background, (array)$options));
                continue;
            }

            $warning = $formatter->getStyle($style);
            $warning->setBackground($background);
            $warning->setForeground($foreground);
            if (isset($colorScheme[$style][2])) {
                $warning->setOption($colorScheme[$style][2]);
            }
        }
    }
}
