<?php

class Common_Logger_Test extends WP_UnitTestCase {

    public function test_logger_instance() {
        $logger = common_logger();
        $this->assertInstanceOf( \Psr\Log\LoggerInterface::class, $logger );
    }

    public function test_basic_logging() {
        $logger = common_logger();
        $this->assertNull( $logger->info( 'Test message from test script' ) );
    }

    public function test_function_chain_building() {
        $chain = common_logger_build_function_chain();
        $this->assertNotEmpty( $chain );
    }

    public function test_origin_detection() {
        $origin = common_logger_detect_enhanced_origin_metadata();
        $this->assertIsArray( $origin );
    }
}