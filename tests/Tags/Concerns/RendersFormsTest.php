<?php

namespace Tests\Tags\Concerns;

use Statamic\Facades\Antlers;
use Statamic\Fields\Field;
use Statamic\Support\Arr;
use Statamic\Tags\Concerns;
use Statamic\Tags\Tags;
use Tests\TestCase;

class RendersFormsTest extends TestCase
{
    const MISSING = 'field is missing from request';

    public function setUp(): void
    {
        parent::setUp();

        $this->tag = new FakeTagWithRendersForms;
    }

    /** @test */
    public function it_renders_form_open_tags()
    {
        $output = $this->tag->formOpen('http://localhost:8000/submit');

        $this->assertStringStartsWith('<form method="POST" action="http://localhost:8000/submit">', $output);
        $this->assertStringContainsString('<input type="hidden" name="_token" value="">', $output);
        $this->assertStringNotContainsString('<input type="hidden" name="_method"', $output);
    }

    /** @test */
    public function it_renders_form_open_tags_with_custom_method()
    {
        $output = $this->tag->formOpen('http://localhost:8000/submit', 'DELETE');

        $this->assertStringStartsWith('<form method="POST" action="http://localhost:8000/submit">', $output);
        $this->assertStringContainsString('<input type="hidden" name="_token" value="">', $output);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="DELETE">', $output);
    }

    /** @test */
    public function it_renders_form_open_tags_with_custom_attributes()
    {
        $output = $this->tag
            ->setParameters([
                'class' => 'mb-1',
                'attr:id' => 'form',
                'method' => 'this should not render',
                'action' => 'this should not render',
            ])
            ->formOpen('http://localhost:8000/submit', 'DELETE');

        $this->assertStringStartsWith('<form method="POST" action="http://localhost:8000/submit" class="mb-1" id="form">', $output);
        $this->assertStringContainsString('<input type="hidden" name="_token" value="">', $output);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="DELETE">', $output);
    }

    /** @test */
    public function it_renders_form_close_tag()
    {
        $this->assertEquals('</form>', $this->tag->formClose());
    }

    /** @test */
    public function it_minifies_space_between_field_html_elements()
    {
        $fields = <<<'EOT'
            <select>
                <option>One</option>
                <option>
                    Two
                </option>
            </select>
            <label>
                <input type="checkbox">
                Option <a href="/link">with link</a> text or <span class="tailwind">style</span> class
            </label>
            <label>
                <input type="radio">
                Intentionally<a href="/link">tight</a>link or<span class="tailwind">style</span>class
            </label>
            <textarea>
                Some <a href="/link">link</a> or <span class="tailwind">styled text
            </textarea>
            <textarea>
                <a href="/link">Start with</a> and end with a <a href="/link">link</a>
            </textarea>
EOT;

        $expected = '<select><option>One</option><option>Two</option></select><label><input type="checkbox">Option <a href="/link">with link</a> text or <span class="tailwind">style</span> class</label><label><input type="radio">Intentionally<a href="/link">tight</a>link or<span class="tailwind">style</span>class</label><textarea>Some <a href="/link">link</a> or <span class="tailwind">styled text</textarea><textarea><a href="/link">Start with</a> and end with a <a href="/link">link</a></textarea>';

        $this->assertEquals($expected, $this->tag->minifyFieldHtml($fields));
    }

    private function createField($type, $value, $default, $old, $config = [])
    {
        $config = array_merge($config, ['type' => $type]);

        if ($default) {
            $config['default'] = $default;
        }

        $field = new Field('test', $config);
        $field->setValue($value);

        if ($old !== self::MISSING) {
            session()->flashInput(['test' => $old]);
            $this->get('/'); // create a request so the session works.
        }

        return $this->tag->getRenderableField($field);
    }

    /**
     * @test
     * @dataProvider renderTextProvider
     */
    public function renders_text_fields($value, $default, $old, $expected)
    {
        $this->textFieldtypeTest('text', $value, $default, $old, $expected);
    }

    private function textFieldtypeTest($fieldtype, $value, $default, $old, $expected)
    {
        $rendered = $this->createField($fieldtype, $value, $default, $old);

        $this->assertSame($expected, $rendered['value']);
        $this->assertStringContainsString('value="'.$rendered['value'].'"', $rendered['field']);
    }

    /**
     * @test
     * @dataProvider renderTextProvider
     */
    public function renders_fallback_fields_as_text_fields($value, $default, $old, $expected)
    {
        (new class extends \Statamic\Fields\Fieldtype
        {
            protected static $handle = 'testing';
        })::register();

        $this->textFieldtypeTest('testing', $value, $default, $old, $expected);
    }

    /**
     * @test
     * @dataProvider renderTextProvider
     */
    public function renders_textarea_fields($value, $default, $old, $expected)
    {
        $rendered = $this->createField('textarea', $value, $default, $old);

        $this->assertSame($expected, $rendered['value']);
        $this->assertStringContainsString('>'.$rendered['value'].'</textarea', $rendered['field']);
    }

    public function renderTextProvider()
    {
        return [
            'no value, missing' => ['value' => null, 'default' => null, 'old' => self::MISSING, 'expectedValue' => null],
            'no value, filled' => ['value' => null, 'default' => null, 'old' => 'old', 'expectedValue' => 'old'],
            'no value, empty' => ['value' => null, 'default' => null, 'old' => null, 'expectedValue' => null],

            'value, missing' => ['value' => 'existing', 'default' => null, 'old' => self::MISSING, 'expectedValue' => 'existing'],
            'value, filled' => ['value' => 'existing', 'default' => null, 'old' => 'old', 'expectedValue' => 'old'],
            'value, empty' => ['value' => 'existing', 'default' => null, 'old' => null, 'expectedValue' => null],

            'no value, default, missing' => ['value' => null, 'default' => 'default', 'old' => self::MISSING, 'expectedValue' => 'default'],
            'no value, default, filled' => ['value' => null, 'default' => 'default', 'old' => 'old', 'expectedValue' => 'old'],
            'no value, default, empty' => ['value' => null, 'default' => 'default', 'old' => null, 'expectedValue' => null],

            'value, default, missing' => ['value' => 'existing', 'default' => 'default', 'old' => self::MISSING, 'expectedValue' => 'existing'],
            'value, default, filled' => ['value' => 'existing', 'default' => 'default', 'old' => 'old', 'expectedValue' => 'old'],
            'value, default, empty' => ['value' => 'existing', 'default' => 'default', 'old' => null, 'expectedValue' => null],
        ];
    }

    /**
     * @test
     * @dataProvider renderToggleProvider
     */
    public function renders_toggles($value, $default, $old, $expected)
    {
        $rendered = $this->createField('toggle', $value, $default, $old);

        $this->assertSame($expected, (bool) $rendered['value']);

        if ($expected) {
            $this->assertStringContainsString('checked', $rendered['field']);
        } else {
            $this->assertStringNotContainsString('checked', $rendered['field']);
        }
    }

    public function renderToggleProvider()
    {
        return [
            'no value, missing' => ['value' => null, 'default' => null, 'old' => self::MISSING, 'expectedValue' => false],
            'no value, checked' => ['value' => null, 'default' => null, 'old' => '1', 'expectedValue' => true],
            'no value, unchecked' => ['value' => null, 'default' => null, 'old' => '0', 'expectedValue' => false],

            'value true, missing' => ['value' => true, 'default' => null, 'old' => self::MISSING, 'expectedValue' => true],
            'value true, checked' => ['value' => true, 'default' => null, 'old' => '1', 'expectedValue' => true],
            'value true, unchecked' => ['value' => true, 'default' => null, 'old' => '0', 'expectedValue' => false],

            'value false, missing' => ['value' => false, 'default' => null, 'old' => self::MISSING, 'expectedValue' => false],
            'value false, checked' => ['value' => false, 'default' => null, 'old' => '1', 'expectedValue' => true],
            'value false, unchecked' => ['value' => false, 'default' => null, 'old' => '0', 'expectedValue' => false],

            'no value, default true, missing' => ['value' => null, 'default' => true, 'old' => self::MISSING, 'expectedValue' => true],
            'no value, default true, checked' => ['value' => null, 'default' => true, 'old' => '1', 'expectedValue' => true],
            'no value, default true, unchecked' => ['value' => null, 'default' => true, 'old' => '0', 'expectedValue' => false],

            'no value, default false, missing' => ['value' => null, 'default' => false, 'old' => self::MISSING, 'expectedValue' => false],
            'no value, default false, checked' => ['value' => null, 'default' => false, 'old' => '1', 'expectedValue' => true],
            'no value, default false, unchecked' => ['value' => null, 'default' => false, 'old' => '0', 'expectedValue' => false],

            'value true, default true, missing' => ['value' => true, 'default' => true, 'old' => self::MISSING, 'expectedValue' => true],
            'value true, default true, checked' => ['value' => true, 'default' => true, 'old' => '1', 'expectedValue' => true],
            'value true, default true, unchecked' => ['value' => true, 'default' => true, 'old' => '0', 'expectedValue' => false],

            'value true, default false, missing' => ['value' => true, 'default' => false, 'old' => self::MISSING, 'expectedValue' => true],
            'value true, default false, checked' => ['value' => true, 'default' => false, 'old' => '1', 'expectedValue' => true],
            'value true, default false, unchecked' => ['value' => true, 'default' => false, 'old' => '0', 'expectedValue' => false],

            'value false, default true, missing' => ['value' => false, 'default' => true, 'old' => self::MISSING, 'expectedValue' => false],
            'value false, default true, checked' => ['value' => false, 'default' => true, 'old' => '1', 'expectedValue' => true],
            'value false, default true, unchecked' => ['value' => false, 'default' => true, 'old' => '0', 'expectedValue' => false],
        ];
    }

    /**
     * @test
     * @dataProvider renderSingleSelectProvider
     */
    public function renders_single_select_fields($value, $default, $old, $expected)
    {
        $rendered = $this->createField('select', $value, $default, $old, [
            'options' => $options = [
                'alfa' => 'Alfa',
                'bravo' => 'Bravo',
                'charlie' => 'Charlie',
            ],
        ]);

        $this->assertStringContainsString('name="test"', $rendered['field']);
        $this->assertStringNotContainsString('multiple', $rendered['field']);

        if ($expected) {
            $unexpected = array_keys(Arr::except($options, $expected));
            $this->assertStringContainsString('value="'.$expected.'" selected', $rendered['field']);
            foreach ($unexpected as $e) {
                $this->assertStringNotContainsString('value="'.$e.'" selected', $rendered['field']);
            }
        } else {
            $this->assertStringNotContainsString('selected', $rendered['field']);
        }
    }

    /**
     * @test
     * @dataProvider renderSingleSelectProvider
     */
    public function renders_radio_fields($value, $default, $old, $expected)
    {
        $rendered = $this->createField('radio', $value, $default, $old, [
            'options' => $options = [
                'alfa' => 'Alfa',
                'bravo' => 'Bravo',
                'charlie' => 'Charlie',
            ],
        ]);

        if ($expected) {
            $unexpected = array_keys(Arr::except($options, $expected));
            $this->assertTrue(
                (bool) preg_match('/value="'.$expected.'"\s+checked/', $rendered['field']),
                'The "'.$expected.'" radio button was not checked within '.$rendered['field'],
            );
            foreach ($unexpected as $e) {
                $this->assertFalse(
                    (bool) preg_match('/value="'.$e.'"\s+checked/', $rendered['field']),
                    'The "'.$expected.'" radio button was checked within '.$rendered['field'],
                );
            }
        } else {
            $this->assertStringNotContainsString('checked', $rendered['field'], 'No radio button should be checked within '.$rendered['field']);
        }
    }

    public function renderSingleSelectProvider()
    {
        return [
            'no value, no default, missing' => ['value' => null, 'default' => null, 'old' => self::MISSING, 'expectedValue' => null],
            'no value, no default, selected' => ['value' => null, 'default' => null, 'old' => 'bravo', 'expectedValue' => 'bravo'],

            'value, no default, missing' => ['value' => 'alfa', 'default' => null, 'old' => self::MISSING, 'expectedValue' => 'alfa'],
            'value, no default, selected' => ['value' => 'alfa', 'default' => null, 'old' => 'bravo', 'expectedValue' => 'bravo'],

            'no value, default, missing' => ['value' => null, 'default' => 'alfa', 'old' => self::MISSING, 'expectedValue' => 'alfa'],
            'no value, default, selected' => ['value' => null, 'default' => 'alfa', 'old' => 'bravo', 'expectedValue' => 'bravo'],

            'value, default, missing' => ['value' => 'alfa', 'default' => 'bravo', 'old' => self::MISSING, 'expectedValue' => 'alfa'],
            'value, default, selected' => ['value' => 'alfa', 'default' => 'bravo', 'old' => 'charlie', 'expectedValue' => 'charlie'],
        ];
    }

    /**
     * @test
     * @dataProvider renderMultipleSelectProvider
     */
    public function renders_multiple_select_fields($value, $default, $old, $expected)
    {
        $rendered = $this->createField('select', $value, $default, $old, [
            'multiple' => true,
            'options' => $options = [
                'alfa' => 'Alfa',
                'bravo' => 'Bravo',
                'charlie' => 'Charlie',
                'delta' => 'Delta',
            ],
        ]);

        $this->assertStringContainsString('name="test[]"', $rendered['field']);
        $this->assertStringContainsString('multiple', $rendered['field']);

        if ($expected) {
            $unexpected = array_diff(array_keys($options), $expected);
            foreach ($expected as $e) {
                $this->assertStringContainsString('value="'.$e.'" selected', $rendered['field']);
            }
            foreach ($unexpected as $e) {
                $this->assertStringNotContainsString('value="'.$e.'" selected', $rendered['field']);
            }
        } else {
            $this->assertStringNotContainsString('selected', $rendered['field']);
        }
    }

    /**
     * @test
     * @dataProvider renderMultipleSelectProvider
     */
    public function renders_checkboxes_fields($value, $default, $old, $expected)
    {
        $rendered = $this->createField('checkboxes', $value, $default, $old, [
            'options' => $options = [
                'alfa' => 'Alfa',
                'bravo' => 'Bravo',
                'charlie' => 'Charlie',
                'delta' => 'Delta',
            ],
        ]);

        if ($expected) {
            $unexpected = array_diff(array_keys($options), $expected);
            foreach ($expected as $e) {
                $this->assertTrue(
                    (bool) preg_match('/value="'.$e.'"\s+checked/', $rendered['field']),
                    'The "'.$e.'" box was not checked within '.$rendered['field'],
                );
            }
            foreach ($unexpected as $e) {
                $this->assertFalse(
                    (bool) preg_match('/value="'.$e.'"\s+checked/', $rendered['field']),
                    'The "'.$e.'" box was checked within '.$rendered['field'],
                );
            }
        } else {
            $this->assertStringNotContainsString('checked', $rendered['field'], 'No boxes should be checked within '.$rendered['field']);
        }
    }

    public function renderMultipleSelectProvider()
    {
        return [
            'no value, no default, missing' => ['value' => null, 'default' => null, 'old' => self::MISSING, 'expectedValue' => null],
            'no value, no default, selected' => ['value' => null, 'default' => null, 'old' => ['alfa'], 'expectedValue' => ['alfa']],
            'no value, no default, selected multiple' => ['value' => null, 'default' => null, 'old' => ['alfa', 'bravo'], 'expectedValue' => ['alfa', 'bravo']],

            'value, no default, missing' => ['value' => ['alfa'], 'default' => null, 'old' => self::MISSING, 'expectedValue' => ['alfa']],
            'value, no default, selected' => ['value' => ['alfa'], 'default' => null, 'old' => ['bravo'], 'expectedValue' => ['bravo']],
            'value, no default, selected multiple' => ['value' => ['alfa'], 'default' => null, 'old' => ['bravo', 'charlie'], 'expectedValue' => ['bravo', 'charlie']],

            'no value, default, missing' => ['value' => null, 'default' => ['alfa'], 'old' => self::MISSING, 'expectedValue' => ['alfa']],
            'no value, default, selected' => ['value' => null, 'default' => ['alfa'], 'old' => ['bravo'], 'expectedValue' => ['bravo']],
            'no value, default, selected multiple' => ['value' => null, 'default' => ['alfa'], 'old' => ['bravo', 'charlie'], 'expectedValue' => ['bravo', 'charlie']],

            'value, default, missing' => ['value' => ['alfa'], 'default' => ['bravo'], 'old' => self::MISSING, 'expectedValue' => ['alfa']],
            'value, default, selected' => ['value' => ['alfa'], 'default' => ['bravo'], 'old' => ['charlie'], 'expectedValue' => ['charlie']],
            'value, default, selected multiple' => ['value' => ['alfa'], 'default' => ['bravo'], 'old' => ['charlie', 'delta'], 'expectedValue' => ['charlie', 'delta']],
        ];
    }
}

class FakeTagWithRendersForms extends Tags
{
    use Concerns\RendersForms;

    public function __construct()
    {
        $this
            ->setParser(Antlers::parser())
            ->setContext([])
            ->setParameters([]);
    }

    public function __call($method, $arguments)
    {
        return $this->{$method}(...$arguments);
    }
}
