<?php
/**
 * Unit tests for HTTP_Request2 package
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2008-2011, Alexey Borzov <avb@php.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *    * The names of the authors may not be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   HTTP
 * @package    HTTP_Request2
 * @author     Alexey Borzov <avb@php.net>
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    SVN: $Id: CookieJarTest.php 308629 2011-02-24 17:34:24Z avb $
 * @link       http://pear.php.net/package/HTTP_Request2
 */

/** Stores cookies and passes them between HTTP requests */
require_once 'HTTP/Request2/CookieJar.php';
/** PHPUnit Test Case */
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * Unit test for HTTP_Request2_CookieJar class
 */
class HTTP_Request2_CookieJarTest extends PHPUnit_Framework_TestCase
{
   /**
    * Cookie jar instance being tested
    * @var HTTP_Request2_CookieJar
    */
    protected $jar;

    protected function setUp()
    {
        $this->jar = new HTTP_Request2_CookieJar();
    }

   /**
    * Test that we can't store junk "cookies" in jar
    *
    * @dataProvider invalidCookieProvider
    * @expectedException HTTP_Request2_LogicException
    */
    public function testStoreInvalid($cookie)
    {
        $this->jar->store($cookie);
    }

   /**
    *
    * @dataProvider noPSLDomainsProvider
    */
    public function testDomainMatchNoPSL($requestHost, $cookieDomain, $expected)
    {
        $this->jar->usePublicSuffixList(false);
        $this->assertEquals($expected, $this->jar->domainMatch($requestHost, $cookieDomain));
    }

   /**
    *
    * @dataProvider PSLDomainsProvider
    */
    public function testDomainMatchPSL($requestHost, $cookieDomain, $expected)
    {
        $this->jar->usePublicSuffixList(true);
        $this->assertEquals($expected, $this->jar->domainMatch($requestHost, $cookieDomain));
    }

    public function testConvertExpiresToISO8601()
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('+1 day');

        $this->jar->store(array(
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => $dt->format(DateTime::COOKIE),
            'secure'  => false
        ));
        $cookies = $this->jar->getAll();
        $this->assertEquals($cookies[0]['expires'], $dt->format(DateTime::ISO8601));
    }

    public function testProblem2038()
    {
        $this->jar->store(array(
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => 'Sun, 01 Jan 2040 03:04:05 GMT',
            'secure'  => false
        ));
        $cookies = $this->jar->getAll();
        $this->assertEquals(array(array(
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => '2040-01-01T03:04:05+0000',
            'secure'  => false
        )), $cookies);
    }

    public function testStoreExpired()
    {
        $base = array(
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'secure'  => false
        );

        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('-1 day');
        $yesterday = $dt->format(DateTime::COOKIE);

        $dt->modify('+2 days');
        $tomorrow = $dt->format(DateTime::COOKIE);

        $this->jar->store($base + array('expires' => $yesterday));
        $this->assertEquals(0, count($this->jar->getAll()));

        $this->jar->store($base + array('expires' => $tomorrow));
        $this->assertEquals(1, count($this->jar->getAll()));
        $this->jar->store($base + array('expires' => $yesterday));
        $this->assertEquals(0, count($this->jar->getAll()));
    }

   /**
    *
    * @dataProvider cookieAndSetterProvider
    */
    public function testGetDomainAndPathFromSetter($cookie, $setter, $expected)
    {
        $this->jar->store($cookie, $setter);
        $expected = array_merge($cookie, $expected);
        $cookies  = $this->jar->getAll();
        $this->assertEquals($expected, $cookies[0]);
    }

   /**
    *
    * @dataProvider cookieMatchProvider
    */
    public function testGetMatchingCookies($url, $expectedCount)
    {
        $cookies = array(
            array('domain' => '.example.com', 'path' => '/', 'secure' => false),
            array('domain' => '.example.com', 'path' => '/', 'secure' => true),
            array('domain' => '.example.com', 'path' => '/path', 'secure' => false),
            array('domain' => '.example.com', 'path' => '/other', 'secure' => false),
            array('domain' => 'example.com', 'path' => '/', 'secure' => false),
            array('domain' => 'www.example.com', 'path' => '/', 'secure' => false),
            array('domain' => 'specific.example.com', 'path' => '/path', 'secure' => false),
            array('domain' => 'nowww.example.com', 'path' => '/', 'secure' => false),
        );

        for ($i = 0; $i < count($cookies); $i++) {
            $this->jar->store($cookies[$i] + array('expires' => null, 'name' => "cookie{$i}", 'value' => "cookie_{$i}_value"));
        }

        $this->assertEquals($expectedCount, count($this->jar->getMatching(new Net_URL2($url))));
    }

    public function testLongestPathFirst()
    {
        $cookie = array(
            'name'    => 'foo',
            'domain'  => '.example.com',
        );
        foreach (array('/', '/specific/path/', '/specific/') as $path) {
            $this->jar->store($cookie + array('path' => $path, 'value' => str_replace('/', '_', $path)));
        }
        $this->assertEquals(
            'foo=_specific_path_; foo=_specific_; foo=_',
            $this->jar->getMatching(new Net_URL2('http://example.com/specific/path/file.php'), true)
        );
    }

    public function testSerializable()
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('+1 day');
        $cookie = array('domain' => '.example.com', 'path' => '/', 'secure' => false, 'value' => 'foo');

        $this->jar->store($cookie + array('name' => 'session', 'expires' => null));
        $this->jar->store($cookie + array('name' => 'long', 'expires' => $dt->format(DateTime::COOKIE)));

        $newJar  = unserialize(serialize($this->jar));
        $cookies = $newJar->getAll();
        $this->assertEquals(1, count($cookies));
        $this->assertEquals('long', $cookies[0]['name']);

        $this->jar->serializeSessionCookies(true);
        $newJar = unserialize(serialize($this->jar));
        $this->assertEquals($this->jar->getAll(), $newJar->getAll());
    }

    public function testRemoveExpiredOnUnserialize()
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('+2 seconds');

        $this->jar->store(array(
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => $dt->format(DateTime::COOKIE),
        ));

        $serialized = serialize($this->jar);
        sleep(2);
        $newJar = unserialize($serialized);
        $this->assertEquals(array(), $newJar->getAll());
    }

    public static function invalidCookieProvider()
    {
        return array(
            array(array()),
            array(array('name' => 'foo')),
            array(array(
                'name'    => 'a name',
                'value'   => 'bar',
                'domain'  => '.example.com',
                'path'    => '/',
            )),
            array(array(
                'name'    => 'foo',
                'value'   => 'a value',
                'domain'  => '.example.com',
                'path'    => '/',
            )),
            array(array(
                'name'    => 'foo',
                'value'   => 'bar',
                'domain'  => '.example.com',
                'path'    => null,
            )),
            array(array(
                'name'    => 'foo',
                'value'   => 'bar',
                'domain'  => null,
                'path'    => '/',
            )),
            array(array(
                'name'    => 'foo',
                'value'   => 'bar',
                'domain'  => '.example.com',
                'path'    => '/',
                'expires' => 'invalid date',
            )),
        );
    }

    public static function noPSLdomainsProvider()
    {
        return array(
            array('localhost', 'localhost', true),
            array('www.example.com', 'www.example.com', true),
            array('127.0.0.1', '127.0.0.1', true),
            array('127.0.0.1', '.0.0.1', false),
            array('www.example.com', '.example.com', true),
            array('deep.within.example.com', '.example.com', true),
            array('example.com', '.com', false),
            array('anotherexample.com', 'example.com', false),
            array('whatever.msk.ru', '.msk.ru', true),
            array('whatever.co.uk', '.co.uk', true),
            array('whatever.uk', '.whatever.uk', true),
            array('whatever.tokyo.jp', '.whatever.tokyo.jp', true),
            array('metro.tokyo.jp', '.metro.tokyo.jp', true),
            array('foo.bar', '.foo.bar', true)
        );
    }

    public static function PSLdomainsProvider()
    {
        return array(
            array('localhost', 'localhost', true),
            array('www.example.com', 'www.example.com', true),
            array('127.0.0.1', '127.0.0.1', true),
            array('127.0.0.1', '.0.0.1', false),
            array('www.example.com', '.example.com', true),
            array('deep.within.example.com', '.example.com', true),
            array('example.com', '.com', false),
            array('anotherexample.com', 'example.com', false),
            array('whatever.msk.ru', '.msk.ru', false),
            array('whatever.co.uk', '.co.uk', false),
            array('whatever.uk', '.whatever.uk', false),
            array('whatever.tokyo.jp', '.whatever.tokyo.jp', false),
            array('metro.tokyo.jp', '.metro.tokyo.jp', true),
            array('foo.bar', '.foo.bar', true)
        );
    }

    public static function cookieAndSetterProvider()
    {
        return array(
            array(
                array(
                    'name'    => 'foo',
                    'value'   => 'bar',
                    'domain'  => null,
                    'path'    => null,
                    'expires' => null,
                    'secure'  => false
                ),
                new Net_Url2('http://example.com/directory/file.php'),
                array(
                    'domain'  => 'example.com',
                    'path'    => '/directory/'
                )
            ),
            array(
                array(
                    'name'    => 'foo',
                    'value'   => 'bar',
                    'domain'  => '.example.com',
                    'path'    => null,
                    'expires' => null,
                    'secure'  => false
                ),
                new Net_Url2('http://example.com/path/to/file.php'),
                array(
                    'path'    => '/path/to/'
                )
            ),
            array(
                array(
                    'name'    => 'foo',
                    'value'   => 'bar',
                    'domain'  => null,
                    'path'    => '/',
                    'expires' => null,
                    'secure'  => false
                ),
                new Net_Url2('http://example.com/another/file.php'),
                array(
                    'domain'  => 'example.com'
                )
            )
        );
    }

    public static function cookieMatchProvider()
    {
        return array(
            array('http://www.example.com/path/file.php', 4),
            array('https://www.example.com/path/file.php', 5),
            array('http://example.com/path/file.php', 3),
            array('http://specific.example.com/path/file.php', 4),
            array('http://specific.example.com/other/file.php', 3),
            array('http://another.example.com/another', 2)
        );
    }
}
?>