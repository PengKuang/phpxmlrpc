<?php

include_once __DIR__ . '/LocalFileTestCase.php';

/**
 * Tests for php files in the 'extras' directory
 *
 */
class ExtraTest extends PhpXmlRpc_LocalFileTestCase
{
    public function set_up()
    {
        $this->args = argParser::getArgs();

        $this->baseUrl = $this->args['HTTPSERVER'] . str_replace( '/demo/server/server.php', '/tests/', $this->args['HTTPURI'] );

        $this->coverageScriptUrl = 'http://' . $this->args['HTTPSERVER'] . '/' . str_replace( '/demo/server/server.php', 'tests/phpunit_coverage.php', $this->args['HTTPURI'] );
    }

    /**
     * @todo collect code coverage for this...
     */
    public function testBenchmark()
    {
        $page = $this->request('benchmark.php');
    }

    /**
     * @todo collect code coverage for this...
     */
    public function testVerifyCompat()
    {
        $page = $this->request('verify_compat.php');
    }
}
