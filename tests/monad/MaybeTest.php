<?php

use Monad\FTry;
use Monad\Option;

class MaybeTest extends \Codeception\Test\Unit
{
    /**
     * Test default handling
     */
    public function test_default_handling()
    {
        $value = Option::create(23)->bind(static function ($v) {
            return $v + 4;
        })->value();
        $this->assertEquals(27, $value);
    }

    public function test_w_custom_check()
    {
        $value = Option::create(23, 23)->bind(static function ($v) {
            return $v + 4;
        })->getOrElse(12);
        $this->assertEquals(12, $value);
    }

    public function test_try()
    {
        $throwRuntimeException = static function () {
            throw new \RuntimeException();
        };
        $addOne = static function () {
            echo 'foo';
        };

        $chain = FTry::create($throwRuntimeException)
            ->bind($addOne)
            ->bind($addOne)
            ->bind($addOne);

        $this->assertInstanceOf(\RuntimeException::class, $chain->value());
        $this->assertEquals(23, $chain->getOrElse(23));
    }

    public function answerSet()
    {
        return [
            [true, '2019-01-01', '2019-01-07'],
            [true, '2019-02-01', '2019-02-07'],
            [true, 'foo bar', '2019-01-01'],
        ];
    }

    /**
     * @dataProvider answerSet
     */
    public function test_try_inside_option($confirmation, $date, $expected)
    {
        $askForUserConfirmation = static function () use ($confirmation) {
            return $confirmation;
        };

        $askForUserDate = static function () use ($date) {
            return $date;
        };

        $addSixDays = static function (\DateTime $input) {
            return $input->add(new DateInterval('P6D'));
        };

        $buildDate = static function ($date) {
            return FTry::create(static function () use ($date) {
                return new DateTime($date);
            })->getOrElse(null);
        };

        $formatDate = static function (DateTime $date) {
            return $date->format('Y-m-d');
        };

        $value = Option::create($askForUserConfirmation(), false)
            ->bind($askForUserDate)
            ->bind($buildDate)
            ->bind($addSixDays)
            ->bind($formatDate)
            ->getOrElse('2019-01-01');

        $this->assertEquals($expected, $value);
    }

    public function test_none_value_transmission()
    {
        $input = 19;

        $value = Option::create($input, 23)
            ->bind(static function ($v) {
                return $v + 4;
            })
            ->bind(static function($v){
                return $v-3;
            })
            ->getOrElse(89);

        $this->assertEquals(89,$value);
    }
}
