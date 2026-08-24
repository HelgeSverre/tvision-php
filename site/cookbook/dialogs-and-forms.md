# Build dialogs and forms

Use `executeDialog()` for a blocking modal interaction. It inserts the dialog into the desktop for the modal loop, returns its closing command, and removes it afterwards.

## Ask for confirmation

`MessageBox::show()` builds and executes a standard modal dialog. Pass a `Group` host explicitly; an `Application` instance is suitable inside an application method.

```php
use HelgeSverre\TurboVision\Dialogs\MessageBox;
use HelgeSverre\TurboVision\Dialogs\MsgBoxFlag;
use HelgeSverre\TurboVision\Events\Cmd;

$result = MessageBox::show(
    $this,
    'Discard unsaved changes?',
    MsgBoxFlag::Confirmation | MsgBoxFlag::YesButton | MsgBoxFlag::NoButton,
);

if ($result === Cmd::Yes) {
    $this->discardChanges();
}
```

Results use command values such as `Cmd::Yes`, `Cmd::No`, `Cmd::Ok`, and `Cmd::Cancel`. Use `MsgBoxFlag::YesNoCancel` or `MsgBoxFlag::OkCancel` for the matching button groups.

## Build a small modal form

Dialog and control rectangles are expressed in character cells; the right and bottom coordinates are exclusive. An `InputLine` capacity includes its terminating slot, so pass `81` for up to 80 editable graphemes.

```php
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\State;

$dialog = new Dialog(Rect::of(0, 0, 48, 12), 'Profile');
$dialog->options |= State::Centered;

$name = new InputLine(Rect::of(14, 2, 42, 3), 81);
$name->setText($this->profileName);
$dialog->insert(new Label(Rect::of(3, 2, 13, 3), '~N~ame:', $name));
$dialog->insert($name);
$dialog->insert(new Button(Rect::of(19, 7, 29, 9), 'O~K~', Cmd::Ok, Button::Default));
$dialog->insert(new Button(Rect::of(32, 7, 43, 9), 'Cancel', Cmd::Cancel));
$dialog->setCurrent($name);

if ($this->executeDialog($dialog) === Cmd::Ok) {
    $this->profileName = $name->text();
}
```

`State::Centered` centers the dialog when its host inserts it. A linked `Label` moves focus to its control when its mnemonic is pressed or the label is clicked. Enter activates the active default button; Escape queues `Cmd::Cancel`.

Read form values only after the affirmative command. Cancellation bypasses validation and should leave application state unchanged.

## Validate a field before accepting

Give `InputLine` a validator. Invalid controls prevent an affirmative modal close and receive focus so the user can correct them.

```php
use HelgeSverre\TurboVision\Validators\FilterValidator;
use HelgeSverre\TurboVision\Validators\RangeValidator;
use HelgeSverre\TurboVision\Validators\StringLookupValidator;

$name = new InputLine(
    Rect::of(3, 3, 29, 4),
    49,
    new FilterValidator('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ -'),
);

$age = new InputLine(Rect::of(3, 6, 12, 7), 6, new RangeValidator(0, 130));
$mode = new InputLine(
    Rect::of(3, 9, 20, 10),
    17,
    new StringLookupValidator(['dark', 'classic', 'mono']),
);
```

- `FilterValidator` accepts only listed characters.
- `RangeValidator` accepts a signed integer within an inclusive range when the form is accepted; while editing, it permits a partial number or sign.
- `StringLookupValidator` requires one exact value from its list.
- `PictureValidator` matches structured input with Turbo Vision picture-mask syntax.

Use `setText()` to preload an editable string and `text()` to read it. `RangeValidator` also transfers a valid field as an integer through `getData()`; treat invalid or cancelled data as uncommitted.

## Add fixed choices and a list

`CheckBoxes` stores a bit per item. `RadioButtons` stores the selected zero-based index. A `ListBox` takes strings and exposes its list plus selected index through `getData()`.

```php
use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\ListBox;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;

$checks = new CheckBoxes(
    Rect::of(3, 12, 24, 15),
    ['~M~ouse support', '~U~nicode cells', '~A~uto save'],
);
$checks->setData(0b011); // first and second choices

$mode = new RadioButtons(Rect::of(27, 12, 43, 15), ['~F~ast', '~S~afe', '~D~ebug']);
$mode->setData(1); // Safe

$list = new ListBox(Rect::of(46, 3, 70, 10));
$list->newList(['Draft', 'Review', 'Published']);
```

Insert a `ScrollBar` before constructing the `ListBox` when the list needs scrolling, then pass it as the third constructor argument.

<DocCapture
  src="/captures/how-to/ui-dialogs.png"
  alt="Centered Profile dialog with editable fields, checked and radio-button choices, a publication-state list, and OK and Cancel buttons"
  caption="The assembled form keeps the related controls together while the desktop remains visible behind it."
/>

## Use standard file and text prompts

Use the supplied dialogs when their behavior fits the task:

```php
use HelgeSverre\TurboVision\Dialogs\FileCommand;
use HelgeSverre\TurboVision\Dialogs\FileDialog;
use HelgeSverre\TurboVision\Dialogs\MessageBox;

$path = MessageBox::input($this, 'Rename', 'New name:', $currentName);

$dialog = new FileDialog('*.php', 'Open a PHP file', '~F~ile name', FileDialog::OpenButton);
if ($this->executeDialog($dialog) === FileCommand::Open) {
    $path = $dialog->getFileName();
}
```

`MessageBox::input()` returns `null` on cancellation. A file dialog may stay open after a wildcard or directory entry while it refreshes its listing; only use its path after its open/replace command returns.

## Verify a form

1. Open the form, use Tab and label mnemonics to move focus, and press Enter on the default button.
2. Submit invalid input and confirm the modal stays open with the invalid field focused.
3. Press Escape and confirm the result is `Cmd::Cancel` without committing values.
4. Repeat with a valid value and confirm only the expected `Cmd::Ok` path updates application state.
