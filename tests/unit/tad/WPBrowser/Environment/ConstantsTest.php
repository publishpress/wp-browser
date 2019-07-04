<?php namespace tad\WPBrowser\Environment;

class ConstantsTest extends \Codeception\Test\Unit
{
    /**
     * Test defined
     */
    public function test_defined()
    {

        $testConstant = $this->getTestConstantName();

        $constants = new Constants();
        $this->assertFalse($constants->defined($testConstant));

        $constants->define($testConstant, 'test');

        $this->assertTrue($constants->defined($testConstant));
    }

    protected function getTestConstantName()
    {
        return '__TEST__' . md5(uniqid('c', true));
    }

    /**
     * Test constant
     */
    public function test_constant()
    {
        $testConstant = $this->getTestConstantName();
        define($testConstant, 'test');

        $constants = new Constants();
        $this->assertEquals('test', $constants->constant($testConstant));
    }

    /**
     * Test defineIfUndefined
     */
    public function test_define_if_undefined()
    {
        $testConstantOne = $this->getTestConstantName();
        $testConstantTwo = $this->getTestConstantName();
        define($testConstantOne, 'test');

        $constants = new Constants();
        $constants->defineIfUndefined($testConstantOne, 'test1');
        $constants->defineIfUndefined($testConstantTwo, 'test2');

        $this->assertEquals('test', constant($testConstantOne));
        $this->assertEquals('test2', constant($testConstantTwo));
    }
}
