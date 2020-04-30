<?php

namespace lucatume\Cli;

use tad\Codeception\SnapshotAssertions\SnapshotAssertions;

class CommandTest extends \Codeception\Test\Unit
{
    use SnapshotAssertions;

    /**
     * Test command throws if each element is not a map entry
     */
    public function test_command_throws_if_each_element_is_not_a_map_entry()
    {
        $this->expectException(CliException::class);

        new Command('test', ['foo', '[bar]', '[--baz]']);
    }

    public function commandCreationDataProvider()
    {
        return [
            // $input, $definition, array $expectedArgs, array $expectedOptions)
            'one req. arg' => [['luca'], ['name' => '_help_'], ['name' => 'luca'], []],
            'greet name [--yell]' => [['luca', '--yell'], ['name' => '_help_', '[--yell]' => '_help_'], ['name' => 'luca'], ['yell' => true]],
            'test operation [-f|--file=] [-d|--dry-run]' => [
                ['delete', '--file=/var/mysql.sock', '-d'],
                ['operation' => '_help_', '[-f|--file=]' => '_help_', '[-d|--dry-run]' => '_help_'],
                ['operation' => 'delete'],
                ['file' => '/var/mysql.sock', 'f' => '/var/mysql.sock', 'dry-run' => true, 'd' => true]
            ],
            'test command [-c|--config=]*' => [
                ['run', '-c=foo.bar', '--config=lorem.dolor'],
                ['command' => '_help_', '[-c|--config=]*' => '_help_'],
                ['command' => 'run'],
                ['c' => ['foo.bar', 'lorem.dolor'], 'config' => ['foo.bar', 'lorem.dolor']]
            ],
            'release [version] [-d|--dry-run]' => [
                ['23.89'],
                ['[version]' => '_help_', '[-d|--dry-run]' => '_help_'],
                ['version' => '23.89'],
                []
            ],
            'greet [name]*' => [
                ['alice', 'bob'],
                ['[name]*' => '_help_'],
                ['name' => ['alice', 'bob']],
                []
            ],
            'test command [target]* [-c|--config=]* [-d|--dry-run]' => [
                ['clean', 'foo', 'bar', '-c=one.conf', '--config=two.conf', '--dry-run'],
                ['command' => '_help_', '[target]*' => '_help_', '[-c|--config=]*' => '_help_', '[-d|--dry-run]' => '_help_'],
                [
                    'command' => 'clean',
                    'target' => ['foo', 'bar']
                ],
                [
                    'c' => ['one.conf', 'two.conf'],
                    'config' => ['one.conf', 'two.conf'],
                    'dry-run' => true,
                    'd' => true,
                ]
            ]
        ];
    }

    /**
     * Test args
     * @dataProvider commandCreationDataProvider
     */
    public function test_command_creation($input, $definition, array $expectedArgs, array $expectedOptions)
    {
        $command = new Command('test', $definition);
        $args = $command->parseInput($input);
        $parsed = $args('_parsed');
        $this->assertEquals($expectedArgs, $parsed['args']);
        $this->assertEquals($expectedOptions, $parsed['options']);
    }

    /**
     * Test args throws if req. arg is missing
     */
    public function test_args_throws_if_req_arg_is_missing()
    {
        $this->expectException(CliException::class);

        $command = new Command('test', ['command' => '_help_', 'suite' => '_help_']);
        $command->parseInput(['foo']);
    }

    /**
     * Test args throws if argument with 1-n values is missing
     */
    public function test_args_throws_if_argument_with_1_n_values_is_missing()
    {
        $this->expectException(CliException::class);

        $command = new Command('test', ['command' => '_help_', 'suite*' => '_help_']);
        $command->parseInput(['foo']);
    }

    /**
     * Test args does not throw if arg with 0-n arguments is missing
     */
    public function test_args_does_not_throw_if_arg_with_0_n_arguments_is_missing()
    {
        $command = new Command('test', ['command' => '_help_', '[suite]*' => '_help_']);
        $command->parseInput(['foo']);
    }

    /**
     * Test args does not throw if optional argument is missing
     */
    public function test_args_does_not_throw_if_optional_argument_is_missing()
    {
        $command = new Command('test', ['command' => '_help_', '[suite]' => '_help_']);
        $command->parseInput(['foo']);
    }

    /**
     * Test args throws if boolean flag option is provided w/ value
     */
    public function test_args_throws_if_boolean_flag_option_is_provided_w_value()
    {
        $this->expectException(CliException::class);

        $command = new Command('greet', ['name' => '_help_', '[--yell]' => '_help_']);
        $command->parseInput(['luca', '--yell=yes']);
    }

    /**
     * Test args throw if option req. value is not provided value
     */
    public function test_args_throw_if_option_req_value_is_not_provided_value()
    {
        $this->expectException(CliException::class);

        $command = new Command('test', ['command' => '_help_', '[--iterations=]' => '_help_']);
        $command->parseInput(['compute', '--iterations']);
    }

    /**
     * Test args throws if option req value is not provided value in short form
     */
    public function test_args_throws_if_option_req_value_is_not_provided_value_in_short_form()
    {
        $this->expectException(CliException::class);

        $command = new Command('test', ['command' => '_help_', '[-i|--iterations=]' => '_help_']);
        $command->parseInput(['compute', '-i']);
    }

    public function helpDataProvider()
    {
        return [
            'req. argument' => [['test' => '_help_']],
            'optional argument' => [['[test]' => '_help_']],
            '1-n argument' => [['test*' => '_help_']],
            '0-n argument' => [['[test]*' => '_help_']],
            'short option' => [['[-d]' => '_help_']],
            'long option' => [['[--dry-run]' => '_help_']],
            'short and long option' => [['[-d|--dry-run]' => '_help_']],
            'short option multi' => [['[-d]*' => '_help_']],
            'long option multi' => [['[--dry-run]*' => '_help_']],
            'short and long option multi' => [['[-d|--dry-run]*' => '_help_']],
            'short option w value' => [['[-d=]' => '_help_']],
            'long option w value' => [['[--dry-run=]' => '_help_']],
            'short and long option w value' => [['[-d|--dry-run=]' => '_help_']],
            'short option multi w value' => [['[-d=]*' => '_help_']],
            'long option multi w value' => [['[--dry-run=]*' => '_help_']],
            'short and long option multi w value' => [['[-d|--dry-run=]*' => '_help_']],
        ];
    }

    /**
     * Test help output
     * @dataProvider helpDataProvider
     */
    public function test_help_output($definition)
    {
        $command = new Command('test', $definition);

        $this->assertMatchesStringSnapshot($command->help());
    }

    public function badDefinitionFormatProvider()
    {
        return [
            'test]' => [['test]'=>'_help_']],
            '[test' => [['[test'=>'_help_']],
           '[test*' => [['[test*'=>'_help_']],
           'test]*' => [['test]*'=>'_help_']],
           'test]=' => [['test]='=>'_help_']],
           '[-f=' => [['[-f='=>'_help_']],
        ] ;
    }
    /**
     * It should throw if definition frag does not match format
     *
     * @test
     * @dataProvider badDefinitionFormatProvider
     */
    public function should_throw_if_definition_frag_does_not_match_format($badDefinitionFormat)
    {
        $this->expectException(CliException::class);

        new Command('test', $badDefinitionFormat);
    }

    /**
     * It should throw if definition includes multiple multi args
     *
     * @test
     */
    public function should_throw_if_definition_includes_multiple_multi_args()
    {
        $this->expectException(CliException::class);

        new Command('test', ['name*'=>'_help_','lastname*' => '_help_']);
    }

    /**
     * It should allow getting the command name
     *
     * @test
     */
    public function should_allow_getting_the_command_name()
    {
        $command = new Command('greet', ['name'=>'_help_']);

        $this->assertEquals('greet', $command->getName());
    }

    /**
     * It should allow comamnds not to have any argument or options
     *
     * @test
     */
    public function should_allow_comamnds_not_to_have_any_argument_or_options()
    {
        $command = new Command('report', []);
    }
}
