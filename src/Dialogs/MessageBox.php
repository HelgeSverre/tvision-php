<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\StaticText;

/** Builders for the reference messageBox()/inputBox convenience dialogs. */
final class MessageBox
{
    /**
     * Run a standard message dialog in a supplied modal host (usually Program or Desktop).
     * The host argument makes modal ownership explicit in PHP instead of relying on C++ globals.
     */
    public static function show(Group $host, string $message, int $options = MsgBoxFlag::OkButton): int
    {
        $extent = $host->getExtent();
        $width = min(max(24, self::messageWidth($message) + 6), max(24, $extent->width()));
        $height = min(max(7, self::messageHeight($message) + 5), max(7, $extent->height()));
        $x = $extent->a->x + max(0, intdiv($extent->width() - $width, 2));
        $y = $extent->a->y + max(0, intdiv($extent->height() - $height, 2));

        return self::showRect($host, Rect::of($x, $y, $x + $width, $y + $height), $message, $options);
    }

    public static function showRect(Group $host, Rect $bounds, string $message, int $options = MsgBoxFlag::OkButton): int
    {
        $dialog = self::dialog($bounds, $message, $options);

        return $host->execView($dialog);
    }

    public static function dialog(Rect $bounds, string $message, int $options = MsgBoxFlag::OkButton): Dialog
    {
        $dialog = new Dialog($bounds, self::titleFor($options));
        $extent = $dialog->getExtent();
        $dialog->insert(new StaticText(Rect::of(2, 1, max(2, $extent->b->x - 2), max(2, $extent->b->y - 3)), $message));

        $buttons = self::buttons($options);
        if ($buttons === []) {
            $buttons = [[MsgBoxText::$okText, Cmd::Ok, ButtonFlag::Default]];
        }
        $total = count($buttons) * 10 + max(0, count($buttons) - 1) * 2;
        $x = max(1, intdiv(max(0, $extent->width() - $total), 2));
        foreach ($buttons as [$title, $command, $flags]) {
            $dialog->insert(new Button(Rect::of($x, max(1, $extent->b->y - 3), $x + 10, max(1, $extent->b->y - 1)), $title, $command, $flags));
            $x += 12;
        }

        return $dialog;
    }

    /**
     * Build and execute a simple InputLine dialog when the InputLine feature is present.
     * @return string|null selected/entered value on OK, otherwise null
     */
    public static function inputBox(Group $host, string $title, string $label, string $value = '', int $maxLen = 80): ?string
    {
        $extent = $host->getExtent();
        $width = min(max(30, min($maxLen + 8, 60)), max(30, $extent->width()));
        $x = $extent->a->x + max(0, intdiv($extent->width() - $width, 2));
        $y = $extent->a->y + max(0, intdiv($extent->height() - 8, 2));
        $dialog = new Dialog(Rect::of($x, $y, $x + $width, $y + 8), $title);
        $dialog->insert(new StaticText(Rect::of(2, 1, $width - 2, 2), $label));
        $input = new InputLine(Rect::of(2, 3, $width - 2, 4), $maxLen + 1);
        $input->setText($value);
        $dialog->insert($input);
        $dialog->insert(new Button(Rect::of($width - 22, 5, $width - 12, 7), MsgBoxText::$okText, Cmd::Ok, ButtonFlag::Default));
        $dialog->insert(new Button(Rect::of($width - 11, 5, $width - 1, 7), MsgBoxText::$cancelText, Cmd::Cancel));
        $result = $host->execView($dialog);

        return $result === Cmd::Ok ? $input->text() : null;
    }

    /** Alias with a concise PHP name. */
    public static function input(Group $host, string $title, string $label, string $value = '', int $maxLen = 80): ?string
    {
        return self::inputBox($host, $title, $label, $value, $maxLen);
    }

    /** @return list<array{string,int,int}> */
    private static function buttons(int $options): array
    {
        $buttons = [];
        if (($options & MsgBoxFlag::YesButton) !== 0) {
            $buttons[] = [MsgBoxText::$yesText, Cmd::Yes, ButtonFlag::Default];
        }
        if (($options & MsgBoxFlag::NoButton) !== 0) {
            $buttons[] = [MsgBoxText::$noText, Cmd::No, $buttons === [] ? ButtonFlag::Default : ButtonFlag::Normal];
        }
        if (($options & MsgBoxFlag::OkButton) !== 0) {
            $buttons[] = [MsgBoxText::$okText, Cmd::Ok, $buttons === [] ? ButtonFlag::Default : ButtonFlag::Normal];
        }
        if (($options & MsgBoxFlag::CancelButton) !== 0) {
            $buttons[] = [MsgBoxText::$cancelText, Cmd::Cancel, ButtonFlag::Normal];
        }

        return $buttons;
    }

    private static function titleFor(int $options): string
    {
        return match ($options & 0x0003) {
            MsgBoxFlag::Error => MsgBoxText::$errorText,
            MsgBoxFlag::Information => MsgBoxText::$informationText,
            MsgBoxFlag::Confirmation => MsgBoxText::$confirmText,
            default => MsgBoxText::$warningText,
        };
    }

    private static function messageWidth(string $message): int
    {
        return max([0, ...array_map(static fn (string $line): int => TerminalText::length($line), preg_split('/\R/u', $message) ?: [])]);
    }

    private static function messageHeight(string $message): int
    {
        return max(1, count(preg_split('/\R/u', $message) ?: []));
    }
}
