# Add dialogs and data

Steps 11–16 build the same dialog in stages. The important transition is not visual: a window-like surface becomes a modal transaction whose values are committed only when the user accepts it.

## Choose modeless or modal behavior

A modeless dialog stays on the desktop alongside other windows:

```php
$dialog = new Dialog(Rect::of(20, 6, 60, 19), 'Preferences');
$this->insertWindow($dialog);
```

The application continues routing events to the desktop. [Guide11.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide11.php) demonstrates this arrangement.

A modal dialog owns interaction until it ends:

```php
$result = $this->executeDialog($dialog);

if ($result === Cmd::Ok) {
    // Commit accepted values.
}
```

Use `Program::executeDialog()` from application code. It executes against the live desktop when available and returns the command that closed the dialog. Escape produces `Cmd::Cancel`; [Guide12.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide12.php) isolates this change.

## Compose controls

Controls are ordinary child views with dialog-local rectangles:

```php
$dialog->insert(new Button(
    Rect::of(15, 10, 25, 12),
    '~O~K',
    Cmd::Ok,
    ButtonFlag::Default,
));
$dialog->insert(new Button(
    Rect::of(28, 10, 38, 12),
    '~C~ancel',
    Cmd::Cancel,
    ButtonFlag::Normal,
));
```

The default button responds to Enter. A normal Cancel button ends the modal scope without requiring child validation. See [Guide13.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide13.php).

Add labeled choices and text input:

```php
$cheeses = new CheckBoxes(
    Rect::of(3, 3, 18, 6),
    SItem::list('~H~varti', '~T~ilset', '~J~arlsberg'),
);
$dialog->insert($cheeses);
$dialog->insert(new Label(Rect::of(2, 2, 12, 3), 'Cheeses', $cheeses));

$instructions = new InputLine(Rect::of(3, 8, 37, 9), 128);
$dialog->insert($instructions);
$dialog->insert(new Label(
    Rect::of(2, 7, 26, 8),
    '~D~elivery instructions',
    $instructions,
));
```

A linked `Label` transfers focus to its control when its mnemonic is used. [Guide14.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide14.php) adds checkbox and radio clusters; [Guide15.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide15.php) adds the input line.

## Treat the dialog as a transaction

The dialog's data contract follows transferable children in insertion order. Preload before execution, read back only after acceptance, and leave stored state untouched on cancellation:

```php
/** @var array{0:int, 1:int, 2:string} */
$settings = [1, 2, 'Phone Mum!'];

$dialog = $this->buildPreferencesDialog();
$dialog->setData($settings);
$result = $this->executeDialog($dialog);

if ($result === Cmd::Ok) {
    /** @var array{0:int, 1:int, 2:string} $accepted */
    $accepted = $dialog->getData();
    $settings = $accepted;
}
```

`CheckBoxes` transfers a bit mask, `RadioButtons` an item index, and `InputLine` a string. Validators participate when a non-cancel command tries to close the dialog. [Guide16.php](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/tutorial/Guide16.php) contains the complete flow.

You now have a complete application progression. For production code, move dialog construction into a dedicated factory and give the tuple a named settings object; [Structure a larger application](/cookbook/structure-an-application) shows one way to make that transition.
