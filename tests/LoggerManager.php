<?php

namespace NaturalCloud\Logger\Test;

use Monolog\Handler\StreamHandler;
use NaturalCloud\Logger\LoggerFactory;
use NaturalCloud\Logger\LogManager;
use NaturalCloud\Logger\Tap\MessageCallableTapFormatter;
use PHPUnit\Framework\TestCase;

class LoggerManager extends TestCase
{

    public function testLog()
    {
        date_default_timezone_set('Asia/Shanghai');
        $arr = require '../src/config/logging.php';
        $log = new LogManager($arr);
        $log->channel('console')->error('error');
        $log->channel('console')->info('info', ['aaa' => 111]);
        $log->channel('console')->warning('warning', ['aaa' => 2222]);
        $log->channel('console')->debug('debug', ['aaa' => 2222]);
        $log->channel('console')->notice('notice', ['aaa' => 2222]);
    }


    public function testChanelfile()
    {
        date_default_timezone_set('Asia/Shanghai');

        $arr = require '../src/config/logging.php';
        $log = new LogManager($arr);
        $logger = $log->channelFile('swoole');
        var_dump($log->channel('swoole'));;

        $logger->info(444444444444);;
    }


    public function testChannels()
    {
        date_default_timezone_set('Asia/Shanghai');

        $arr = require '../src/config/logging.php';
        $log = new LogManager($arr);

        foreach (['single', 'daily', 'emergency'] as $channel) {
            $log->channel($channel)->info($channel . ': test');
        }
        sleep(3);

        foreach (['single', 'daily', 'emergency'] as $channel) {
            $this->assertFileNotExists(rtrim(sys_get_temp_dir(), '/') . "/$channel.log");
        }
    }


    public function testCustomerDriver()
    {
        date_default_timezone_set('Asia/Shanghai');

        $arr = require '../src/config/logging.php';
        $log = new LogManager($arr);
        $log->extend('json', function () {
        });
        // todo

    }


    public function testSimpleLogger()
    {
        $arr = require '../src/config/simple_logger.php';
        $logF = new LoggerFactory($arr);
        $logF->get()->info('simple_logger.php test');
    }


    public function testJsonFrom()
    {
        $arr = require '../src/config/logging.php';
        $logF = new LogManager($arr);
        $logF->channel('json')->error('json error test');
    }


    public function testMessageCallable()
    {
        $arr = require '../src/config/logging.php';
        $logF = new LogManager($arr);
        $logF->channelFile('messagecallable', ['tap' => [MessageCallableTapFormatter::class]])->error([1, 2, 3]);
        $logF->channelFile('messagecallable')->info((object)[456]);
    }


}
