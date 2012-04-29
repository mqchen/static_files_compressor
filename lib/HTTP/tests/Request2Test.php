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
 * @version    SVN: $Id: Request2Test.php 308735 2011-02-27 20:31:28Z avb $
 * @link       http://pear.php.net/package/HTTP_Request2
 */

/**
 * Class representing a HTTP request
 */
require_once 'HTTP/Request2.php';

/**
 * PHPUnit Test Case
 */
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * Unit test for HTTP_Request2 class
 */
class HTTP_Request2Test extends PHPUnit_Framework_TestCase
{
    public function testConstructorSetsDefaults()
    {
        $url = new Net_URL2('http://www.example.com/foo');
        $req = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array('connect_timeout' => 666));

        $this->assertSame($url, $req->getUrl());
        $this->assertEquals(HTTP_Request2::METHOD_POST, $req->getMethod());
        $this->assertEquals(666, $req->getConfig('connect_timeout'));
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testSetUrl()
    {
        $urlString = 'http://www.example.com/foo/bar.php';
        $url       = new Net_URL2($urlString);

        $req1 = new HTTP_Request2();
        $req1->setUrl($url);
        $this->assertSame($url, $req1->getUrl());

        $req2 = new HTTP_Request2();
        $req2->setUrl($urlString);
        $this->assertType('Net_URL2', $req2->getUrl());
        $this->assertEquals($urlString, $req2->getUrl()->getUrl());

        $req3 = new HTTP_Request2();
        $req3->setUrl(array('This will cause an error'));
    }

    public function testConvertUserinfoToAuth()
    {
        $req = new HTTP_Request2();
        $req->setUrl('http://foo:b%40r@www.example.com/');

        $this->assertEquals('', (string)$req->getUrl()->getUserinfo());
        $this->assertEquals(
            array('user' => 'foo', 'password' => 'b@r', 'scheme' => HTTP_Request2::AUTH_BASIC),
            $req->getAuth()
        );
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testSetMethod()
    {
        $req = new HTTP_Request2();
        $req->setMethod(HTTP_Request2::METHOD_PUT);
        $this->assertEquals(HTTP_Request2::METHOD_PUT, $req->getMethod());

        $req->setMethod('Invalid method');
    }

    public function testSetAndGetConfig()
    {
        $req = new HTTP_Request2();
        $this->assertArrayHasKey('connect_timeout', $req->getConfig());

        $req->setConfig(array('connect_timeout' => 123));
        $this->assertEquals(123, $req->getConfig('connect_timeout'));
        try {
            $req->setConfig(array('foo' => 'unknown parameter'));
            $this->fail('Expected HTTP_Request2_LogicException was not thrown');
        } catch (HTTP_Request2_LogicException $e) {}

        try {
            $req->getConfig('bar');
            $this->fail('Expected HTTP_Request2_LogicException was not thrown');
        } catch (HTTP_Request2_LogicException $e) {}
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testHeaders()
    {
        $req = new HTTP_Request2();
        $autoHeaders = $req->getHeaders();

        $req->setHeader('Foo', 'Bar');
        $req->setHeader('Foo-Bar: value');
        $req->setHeader(array('Another-Header' => 'another value', 'Yet-Another: other_value'));
        $this->assertEquals(
            array('foo-bar' => 'value', 'another-header' => 'another value',
            'yet-another' => 'other_value', 'foo' => 'Bar') + $autoHeaders,
            $req->getHeaders()
        );

        $req->setHeader('FOO-BAR');
        $req->setHeader(array('aNOTHER-hEADER'));
        $this->assertEquals(
            array('yet-another' => 'other_value', 'foo' => 'Bar') + $autoHeaders,
            $req->getHeaders()
        );

        $req->setHeader('Invalid header', 'value');
    }

    public function testBug15937()
    {
        $req = new HTTP_Request2();
        $autoHeaders = $req->getHeaders();

        $req->setHeader('Expect: ');
        $req->setHeader('Foo', '');
        $this->assertEquals(
            array('expect' => '', 'foo' => '') + $autoHeaders,
            $req->getHeaders()
        );
    }

    public function testRequest17507()
    {
        $req = new HTTP_Request2();

        $req->setHeader('accept-charset', 'iso-8859-1');
        $req->setHeader('accept-charset', array('windows-1251', 'utf-8'), false);

        $req->setHeader(array('accept' => 'text/html'));
        $req->setHeader(array('accept' => 'image/gif'), null, false);

        $headers = $req->getHeaders();

        $this->assertEquals('iso-8859-1, windows-1251, utf-8', $headers['accept-charset']);
        $this->assertEquals('text/html, image/gif', $headers['accept']);
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testCookies()
    {
        $req = new HTTP_Request2();
        $req->addCookie('name', 'value');
        $req->addCookie('foo', 'bar');
        $headers = $req->getHeaders();
        $this->assertEquals('name=value; foo=bar', $headers['cookie']);

        $req->addCookie('invalid cookie', 'value');
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testPlainBody()
    {
        $req = new HTTP_Request2();
        $req->setBody('A string');
        $this->assertEquals('A string', $req->getBody());

        $req->setBody(dirname(__FILE__) . '/_files/plaintext.txt', true);
        $headers = $req->getHeaders();
        $this->assertRegexp(
            '!^(text/plain|application/octet-stream)!',
            $headers['content-type']
        );
        $this->assertEquals('This is a test.', fread($req->getBody(), 1024));

        $req->setBody('missing file', true);
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testRequest16863()
    {
        $req = new HTTP_Request2();
        $req->setBody(fopen(dirname(__FILE__) . '/_files/plaintext.txt', 'rb'));
        $headers = $req->getHeaders();
        $this->assertEquals('application/octet-stream', $headers['content-type']);

        $req->setBody(fopen('php://input', 'rb'));
    }

    public function testUrlencodedBody()
    {
        $req = new HTTP_Request2(null, HTTP_Request2::METHOD_POST);
        $req->addPostParameter('foo', 'bar');
        $req->addPostParameter(array('baz' => 'quux'));
        $req->addPostParameter('foobar', array('one', 'two'));
        $this->assertEquals(
            'foo=bar&baz=quux&foobar%5B0%5D=one&foobar%5B1%5D=two',
            $req->getBody()
        );

        $req->setConfig(array('use_brackets' => false));
        $this->assertEquals(
            'foo=bar&baz=quux&foobar=one&foobar=two',
            $req->getBody()
        );
    }

    public function testRequest15368()
    {
        $req = new HTTP_Request2(null, HTTP_Request2::METHOD_POST);
        $req->addPostParameter('foo', 'te~st');
        $this->assertContains('~', $req->getBody());
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testUpload()
    {
        $req = new HTTP_Request2(null, HTTP_Request2::METHOD_POST);
        $req->addUpload('upload', dirname(__FILE__) . '/_files/plaintext.txt');

        $headers = $req->getHeaders();
        $this->assertEquals('multipart/form-data', $headers['content-type']);

        $req->addUpload('upload_2', 'missing file');
    }

    public function testPropagateUseBracketsToNetURL2()
    {
        $req = new HTTP_Request2('http://www.example.com/', HTTP_Request2::METHOD_GET,
                                 array('use_brackets' => false));
        $req->getUrl()->setQueryVariable('foo', array('bar', 'baz'));
        $this->assertEquals('http://www.example.com/?foo=bar&foo=baz', $req->getUrl()->__toString());

        $req->setConfig('use_brackets', true)->setUrl('http://php.example.com/');
        $req->getUrl()->setQueryVariable('foo', array('bar', 'baz'));
        $this->assertEquals('http://php.example.com/?foo[0]=bar&foo[1]=baz', $req->getUrl()->__toString());
    }

    public function testSetBodyRemovesPostParameters()
    {
        $req = new HTTP_Request2('http://www.example.com/', HTTP_Request2::METHOD_POST);
        $req->addPostParameter('foo', 'bar');
        $req->setBody('');
        $this->assertEquals('', $req->getBody());
    }

    public function testPostParametersPrecedeSetBodyForPost()
    {
        $req = new HTTP_Request2('http://www.example.com/', HTTP_Request2::METHOD_POST);
        $req->setBody('Request body');
        $req->addPostParameter('foo', 'bar');

        $this->assertEquals('foo=bar', $req->getBody());

        $req->setMethod(HTTP_Request2::METHOD_PUT);
        $this->assertEquals('Request body', $req->getBody());
    }

    public function testSetMultipartBody()
    {
        require_once 'HTTP/Request2/MultipartBody.php';

        $req = new HTTP_Request2('http://www.example.com/', HTTP_Request2::METHOD_POST);
        $body = new HTTP_Request2_MultipartBody(array('foo' => 'bar'), array());
        $req->setBody($body);
        $this->assertSame($body, $req->getBody());
    }

    public function testBug17460()
    {
        $req = new HTTP_Request2('http://www.example.com/', HTTP_Request2::METHOD_POST);
        $req->addPostParameter('foo', 'bar')
            ->setHeader('content-type', 'application/x-www-form-urlencoded; charset=UTF-8');

        $this->assertEquals('foo=bar', $req->getBody());
    }

   /**
    *
    * @expectedException HTTP_Request2_LogicException
    */
    public function testCookieJar()
    {
        $req = new HTTP_Request2();
        $this->assertNull($req->getCookieJar());

        $req->setCookieJar();
        $jar = $req->getCookieJar();
        $this->assertType('HTTP_Request2_CookieJar', $jar);

        $req2 = new HTTP_Request2();
        $req2->setCookieJar($jar);
        $this->assertSame($jar, $req2->getCookieJar());

        $req2->setCookieJar(null);
        $this->assertNull($req2->getCookieJar());

        $req2->setCookieJar('foo');
    }

    public function testAddCookieToJar()
    {
        $req = new HTTP_Request2();
        $req->setCookieJar();

        try {
            $req->addCookie('foo', 'bar');
            $this->fail('Expected HTTP_Request2_Exception was not thrown');
        } catch (HTTP_Request2_LogicException $e) { }

        $req->setUrl('http://example.com/path/file.php');
        $req->addCookie('foo', 'bar');

        $this->assertArrayNotHasKey('cookie', $req->getHeaders());
        $cookies = $req->getCookieJar()->getAll();
        $this->assertEquals(
            array(
                'name'    => 'foo',
                'value'   => 'bar',
                'domain'  => 'example.com',
                'path'    => '/path/',
                'expires' => null,
                'secure'  => false
            ),
            $cookies[0]
        );
    }
}
?>