<?php declare(strict_types=1);
namespace R3T\Util;
class TerminalHelper{
    public static function color(string $color, string $text = ''):string{
        return match($color){
            'red' => "\033[31m".$text."\033[0m",
            'green' => "\033[32m".$text."\033[0m",
            'yellow' => "\033[33m".$text."\033[0m",
            'blue' => "\033[34m".$text."\033[0m",
            'magenta' => "\033[35m".$text."\033[0m",
            'cyan' => "\033[36m".$text."\033[0m",
            'white' => "\033[37m".$text."\033[0m",
            default => $text,
        };
    }
    private static $spinnerFrames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    private static $spinnerIndex = 0;
    private static $spinnerLastUpdate = 0;

    public static function spinnerStart(string $text = ''): void
    {
        self::$spinnerIndex = 0;
        self::$spinnerLastUpdate = 0;

        echo $text;

        // Save the cursor position immediately after the initial text.
        echo "\033[s";

        self::spinnerUpdate(true);
    }

    public static function spinnerUpdate(bool $force = false): void
    {
        $now = microtime(true);

        if(!$force && $now - self::$spinnerLastUpdate < 0.1){
            return;
        }

        self::$spinnerLastUpdate = $now;

        $frame = self::$spinnerFrames[self::$spinnerIndex++ % count(self::$spinnerFrames)];

        // Return to the spinner position and overwrite only its character.
        echo "\033[u".$frame;
        flush();
    }

    public static function spinnerFinish(string $text): void
    {
        // Return to the spinner position and overwrite it with final text.
        echo "\033[u".$text.PHP_EOL;
        flush();
    }
}