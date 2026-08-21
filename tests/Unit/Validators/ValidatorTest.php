<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Validators\FilterValidator;
use HelgeSverre\TurboVision\Validators\PicResult;
use HelgeSverre\TurboVision\Validators\PictureValidator;
use HelgeSverre\TurboVision\Validators\RangeValidator;
use HelgeSverre\TurboVision\Validators\StringLookupValidator;
use HelgeSverre\TurboVision\Validators\Validator;
use HelgeSverre\TurboVision\Validators\ValidatorTransfer;
use InvalidArgumentException;

test('base and filter validators have predictable status and error behavior', function (): void {
    $base = new Validator();
    $text = 'anything';
    expect($base->status)->toBe(Validator::StatusOk)
        ->and($base->isValidInput($text))->toBeTrue()
        ->and($base->validate($text))->toBeTrue();

    $filter = new FilterValidator('abc');
    $invalid = 'abd';
    expect($filter->isValidInput($invalid))->toBeFalse()
        ->and($filter->validate($invalid))->toBeFalse()
        ->and($filter->lastError)->toBe('Invalid character in input.');
});

test('range validator enforces integer syntax, inclusivity and native int conversion', function (): void {
    $range = new RangeValidator(-2, 4);
    expect($range->isValid('-2'))->toBeTrue()
        ->and($range->isValid('+4'))->toBeTrue()
        ->and($range->isValid(''))->toBeFalse()
        ->and($range->isValid('+'))->toBeFalse()
        ->and($range->isValid('04'))->toBeTrue()
        ->and($range->isValid('5'))->toBeFalse();

    $text = '';
    $number = -1;
    expect($range->transfer($text, $number, ValidatorTransfer::SetData))->toBe(PHP_INT_SIZE)
        ->and($text)->toBe('-1');
    $number = null;
    expect($range->transfer($text, $number, ValidatorTransfer::GetData))->toBe(PHP_INT_SIZE)
        ->and($number)->toBe(-1);
    expect(fn () => new RangeValidator(2, 1))->toThrow(InvalidArgumentException::class);
});

test('range validator distinguishes editable prefixes from completed values', function (): void {
    $positive = new RangeValidator(10, 20);
    $empty = '';
    $prefix = '1';
    $minus = '-';
    expect($positive->isValidInput($empty))->toBeTrue()
        ->and($positive->isValidInput($prefix))->toBeTrue()
        ->and($positive->isValidInput($minus))->toBeFalse()
        ->and($positive->isValid($prefix))->toBeFalse();

    $signed = new RangeValidator(-20, -10);
    expect($signed->isValidInput($minus))->toBeTrue()
        ->and($signed->isValid($minus))->toBeFalse();
});

test('string lookup uses exact matching and replaces its immutable list safely', function (): void {
    $lookup = new StringLookupValidator(['Zulu', 'Alpha', 'Alpha']);
    expect($lookup->strings())->toBe(['Alpha', 'Zulu'])
        ->and($lookup->isValid('Alpha'))->toBeTrue()
        ->and($lookup->isValid('alpha'))->toBeFalse();

    $lookup->newStringList(['one', 'two']);
    expect($lookup->isValid('Zulu'))->toBeFalse()
        ->and($lookup->isValid('two'))->toBeTrue();
});

test('picture validator supports formatting, literals, optional groups, alternatives and syntax status', function (): void {
    $zip = new PictureValidator('#####-###', true);
    $input = '12345';
    expect($zip->picture($input, true))->toBe(PicResult::Incomplete)
        ->and($input)->toBe('12345-');
    $input = '12345-678';
    expect($zip->picture($input))->toBe(PicResult::Complete);

    $letters = new PictureValidator('&&');
    $input = 'ab';
    expect($letters->isValidInput($input))->toBeTrue()->and($input)->toBe('AB');

    $optional = new PictureValidator('##[-##]');
    expect($optional->isValid('12'))->toBeTrue()
        ->and($optional->isValid('12-34'))->toBeTrue();

    $alternative = new PictureValidator('{##,??}');
    expect($alternative->isValid('42'))->toBeTrue()
        ->and($alternative->isValid('ab'))->toBeTrue()
        ->and($alternative->isValid('4a'))->toBeFalse();

    $invalid = new PictureValidator('[##');
    expect($invalid->status)->toBe(Validator::StatusSyntax)
        ->and($invalid->isValid('12'))->toBeFalse();
});

test('picture validator honors numeric and unbounded repetition counts', function (): void {
    $exact = new PictureValidator('*3{#}');
    expect($exact->isValid('123'))->toBeTrue()
        ->and($exact->isValid('12'))->toBeFalse()
        ->and($exact->isValid('1234'))->toBeFalse();

    $slotExact = new PictureValidator('*2#-#');
    expect($slotExact->isValid('12-3'))->toBeTrue()
        ->and($slotExact->isValid('1-3'))->toBeFalse();

    $unbounded = new PictureValidator('*#');
    expect($unbounded->isValid('123456'))->toBeTrue()
        ->and($unbounded->isValid('12a'))->toBeFalse();

    // As in the upstream parser, explicit zero still means unbounded.
    $zero = new PictureValidator('*0#');
    expect($zero->isValid('123456'))->toBeTrue();
});

test('picture validator bounds ambiguous alternatives without exhausting memory', function (): void {
    $validator = new PictureValidator(str_repeat('{?,&}', 20));
    $input = str_repeat('a', 20);

    expect($validator->picture($input))->toBe(PicResult::Error)
        ->and($validator->status)->toBe(Validator::StatusOk);
});
