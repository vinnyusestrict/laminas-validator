<?php

namespace LaminasTest\Validator\File;

use Laminas\Validator\Exception\InvalidArgumentException;
use Laminas\Validator\File;
use PHPUnit\Framework\TestCase;

use function basename;
use function current;
use function is_array;

use const UPLOAD_ERR_NO_FILE;

/**
 * @group      Laminas_Validator
 */
class WordCountTest extends TestCase
{
    /**
     * @psalm-return array<array-key, array{
     *     0: int|array<string, int>,
     *     1: string|array{
     *         tmp_name: string,
     *         name: string,
     *         size: int,
     *         error: int,
     *         type: string
     *     },
     *     2: bool
     * }>
     */
    public function basicBehaviorDataProvider(): array
    {
        $testFile = __DIR__ . '/_files/wordcount.txt';
        $testData = [
            //    Options, isValid Param, Expected value
            [15,      $testFile,     true],
            [4,       $testFile,     false],
            [['min' => 0, 'max' => 10], $testFile, true],
            [['min' => 10, 'max' => 15], $testFile, false],
        ];

        // Dupe data in File Upload format
        foreach ($testData as $data) {
            $fileUpload = [
                'tmp_name' => $data[1],
                'name'     => basename($data[1]),
                'size'     => 200,
                'error'    => 0,
                'type'     => 'text',
            ];
            $testData[] = [$data[0], $fileUpload, $data[2]];
        }
        return $testData;
    }

    /**
     * Ensures that the validator follows expected behavior
     *
     * @dataProvider basicBehaviorDataProvider
     * @param int|array $options
     * @param string|array $isValidParam
     */
    public function testBasic($options, $isValidParam, bool $expected): void
    {
        $validator = new File\WordCount($options);
        $this->assertEquals($expected, $validator->isValid($isValidParam));
    }

    /**
     * Ensures that the validator follows expected behavior for legacy Laminas\Transfer API
     *
     * @dataProvider basicBehaviorDataProvider
     * @param int|array $options
     * @param string|array $isValidParam
     */
    public function testLegacy($options, $isValidParam, bool $expected): void
    {
        if (is_array($isValidParam)) {
            $validator = new File\WordCount($options);
            $this->assertEquals($expected, $validator->isValid($isValidParam['tmp_name'], $isValidParam));
        }
    }

    /**
     * Ensures that getMin() returns expected value
     *
     * @return void
     */
    public function testGetMin()
    {
        $validator = new File\WordCount(['min' => 1, 'max' => 5]);
        $this->assertEquals(1, $validator->getMin());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than or equal');
        new File\WordCount(['min' => 5, 'max' => 1]);
    }

    /**
     * Ensures that setMin() returns expected value
     *
     * @return void
     */
    public function testSetMin()
    {
        $validator = new File\WordCount(['min' => 1000, 'max' => 10000]);
        $validator->setMin(100);
        $this->assertEquals(100, $validator->getMin());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('less than or equal');
        $validator->setMin(20000);
    }

    /**
     * Ensures that getMax() returns expected value
     *
     * @return void
     */
    public function testGetMax()
    {
        $validator = new File\WordCount(['min' => 1, 'max' => 100]);
        $this->assertEquals(100, $validator->getMax());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than or equal');
        new File\WordCount(['min' => 5, 'max' => 1]);
    }

    /**
     * Ensures that setMax() returns expected value
     *
     * @return void
     */
    public function testSetMax()
    {
        $validator = new File\WordCount(['min' => 1000, 'max' => 10000]);
        $validator->setMax(1000000);
        $this->assertEquals(1000000, $validator->getMax());

        $validator->setMin(100);
        $this->assertEquals(1000000, $validator->getMax());
    }

    /**
     * @group Laminas-11258
     */
    public function testLaminas11258(): void
    {
        $validator = new File\WordCount(['min' => 1, 'max' => 10000]);
        $this->assertFalse($validator->isValid(__DIR__ . '/_files/nofile.mo'));
        $this->assertArrayHasKey('fileWordCountNotFound', $validator->getMessages());
        $this->assertStringContainsString('does not exist', current($validator->getMessages()));
    }

    public function testEmptyFileShouldReturnFalseAndDisplayNotFoundMessage(): void
    {
        $validator = new File\WordCount();

        $this->assertFalse($validator->isValid(''));
        $this->assertArrayHasKey(File\WordCount::NOT_FOUND, $validator->getMessages());

        $filesArray = [
            'name'     => '',
            'size'     => 0,
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
            'type'     => '',
        ];

        $this->assertFalse($validator->isValid($filesArray));
        $this->assertArrayHasKey(File\WordCount::NOT_FOUND, $validator->getMessages());
    }

    public function testCanSetMinValueUsingOptionsArray(): void
    {
        $validator = new File\WordCount(['min' => 1000, 'max' => 10000]);
        $minValue  = 33;
        $options   = ['min' => $minValue];

        $validator->setMin($options);
        $this->assertSame($minValue, $validator->getMin());
    }

    /**
     * @psalm-return array<string, array{0: mixed}>
     */
    public function invalidMinMaxValues(): array
    {
        return [
            'null'               => [null],
            'true'               => [true],
            'false'              => [false],
            'non-numeric-string' => ['not-a-good-value'],
            'array-without-keys' => [[100]],
            'object'             => [(object) []],
        ];
    }

    /**
     * @dataProvider invalidMinMaxValues
     * @param mixed $value
     */
    public function testSettingMinValueRaisesExceptionForInvalidType($value): void
    {
        $validator = new File\WordCount(['min' => 1000, 'max' => 10000]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid options to validator provided');
        $validator->setMin($value);
    }

    public function testCanSetMaxValueUsingOptionsArray(): void
    {
        $validator = new File\WordCount(['min' => 1000, 'max' => 10000]);
        $maxValue  = 33333333;
        $options   = ['max' => $maxValue];

        $validator->setMax($options);
        $this->assertSame($maxValue, $validator->getMax());
    }

    /**
     * @dataProvider invalidMinMaxValues
     * @param mixed $value
     */
    public function testSettingMaxValueRaisesExceptionForInvalidType($value): void
    {
        $validator = new File\WordCount(['min' => 1000, 'max' => 10000]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid options to validator provided');
        $validator->setMax($value);
    }

    public function testIsValidShouldThrowInvalidArgumentExceptionForArrayNotInFilesFormat(): void
    {
        $validator = new File\WordCount(['min' => 1, 'max' => 10000]);
        $value     = ['foo' => 'bar'];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value array must be in $_FILES format');
        $validator->isValid($value);
    }

    public function testConstructCanAcceptAllOptionsAsDiscreteArguments(): void
    {
        $min       = 1;
        $max       = 10000;
        $validator = new File\WordCount($min, $max);

        $this->assertSame($min, $validator->getMin());
        $this->assertSame($max, $validator->getMax());
    }
}
