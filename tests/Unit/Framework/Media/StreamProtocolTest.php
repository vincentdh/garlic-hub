<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

declare(strict_types=1);

namespace Tests\Unit\Framework\Media;

use App\Framework\Media\StreamProtocol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class StreamProtocolTest extends TestCase
{
  /**
   * @return array<string, array{string}>
   */
  public static function supportedSchemeProvider(): array
  {
    return [
      'rtsp lowercase'     => ['rtsp://cam.local/live'],
      'rtsps lowercase'    => ['rtsps://cam.local/live'],
      'rtsp uppercase'     => ['RTSP://cam.local/live'],
      'rtsps mixed case'   => ['RtSpS://cam.local/live'],
    ];
  }

  /**
   * @return array<string, array{string}>
   */
  public static function unsupportedSchemeProvider(): array
  {
    return [
      'http'             => ['http://example.com/live'],
      'https'            => ['https://example.com/live'],
      'ftp'              => ['ftp://example.com/file'],
      'file'             => ['file:///tmp/live'],
      'scheme in path'   => ['http://example.com/rtsp://cam.local/live'],
      'rtsp as substring' => ['xrtsp://cam.local/live'],
    ];
  }

  /**
   * @return array<string, array{string}>
   */
  public static function validStreamUrlProvider(): array
  {
    return [
      'rtsp with port and path'       => ['rtsp://cam.local:554/live'],
      'rtsp without port'             => ['rtsp://cam.local/live'],
      'rtsp host only'                => ['rtsp://cam.local'],
      'rtsp with port only'           => ['rtsp://cam.local:554'],
      'rtsp with IPv4 host'           => ['rtsp://192.168.1.10:8554/stream1'],
      'rtsp with IPv6 host'           => ['rtsp://[::1]:554/live'],
      'rtsp with nested path'         => ['rtsp://cam.local/a/b/c'],
      'rtsp with query string'        => ['rtsp://cam.local/live?channel=2'],
      'rtsps with port and path'      => ['rtsps://cam.local:322/live'],
      'rtsps without port'           => ['rtsps://cam.local/live'],
      'uppercase scheme'              => ['RTSP://cam.local/live'],
    ];
  }

  /**
   * @return array<string, array{string}>
   */
  public static function invalidStreamUrlProvider(): array
  {
    return [
      'http scheme'                   => ['http://cam.local/live'],
      'unknown scheme'                => ['ftp://cam.local/live'],
      'missing slashes after scheme'  => ['rtsp:cam.local/live'],
      'host with space'               => ['rtsp://ca m.local/live'],
      'empty port'                    => ['rtsp://cam.local:/live'],
      'trailing slash without path'   => ['rtsp://cam.local/'],
      'user without password'         => ['rtsp://user@cam.local/live'],
      'user and password'             => ['rtsp://user:secret@cam.local/live'],
      'password without user'         => ['rtsp://:secret@cam.local/live'],
      'rtsps with credentials'        => ['rtsps://user:secret@cam.local/live'],
    ];
  }

  #[Group('units')]
  #[DataProvider('supportedSchemeProvider')]
  public function testSupportedSchemesAreRecognised(string $url): void
  {
    static::assertTrue(StreamProtocol::isSupported($url));
  }

  #[Group('units')]
  #[DataProvider('unsupportedSchemeProvider')]
  public function testUnsupportedSchemesAreRejected(string $url): void
  {
    static::assertFalse(StreamProtocol::isSupported($url));
  }

  #[Group('units')]
  public function testIsSupportedThrowsWhenUrlHasNoScheme(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    StreamProtocol::isSupported('cam.local/live');
  }

  #[Group('units')]
  public function testIsSupportedThrowsOnEmptyString(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    StreamProtocol::isSupported('');
  }

  #[Group('units')]
  #[DataProvider('validStreamUrlProvider')]
  public function testValidStreamUrlsAreAccepted(string $url): void
  {
    static::assertTrue(StreamProtocol::isValidStreamUrl($url));
  }

  #[Group('units')]
  #[DataProvider('invalidStreamUrlProvider')]
  public function testInvalidStreamUrlsAreRejected(string $url): void
  {
    static::assertFalse(StreamProtocol::isValidStreamUrl($url));
  }

  #[Group('units')]
  public function testIsValidStreamUrlReturnsFalseForUnsupportedSchemeWithoutThrowing(): void
  {
    static::assertFalse(StreamProtocol::isValidStreamUrl('http://cam.local/live'));
  }

  /**
   * @return array<string, array{string}>
   */
  public static function malformedStreamUrlProvider(): array
  {
    return [
      'no scheme'                     => ['cam.local/live'],
      'empty host'                    => ['rtsp://'],
      'empty host with port'          => ['rtsp://:554/live'],
      'non numeric port'              => ['rtsp://cam.local:abc/live'],
    ];
  }

  #[Group('units')]
  #[DataProvider('malformedStreamUrlProvider')]
  public function testMalformedStreamUrlsThrowException(string $url): void
  {
    $this->expectException(\InvalidArgumentException::class);
    StreamProtocol::isValidStreamUrl($url);
  }

  #[Group('units')]
  public function testGetStreamUrlPatternReturnsNullForUnknownProtocol(): void
  {
    static::assertNull(StreamProtocol::getStreamUrlPattern('http'));
    static::assertNull(StreamProtocol::getStreamUrlPattern(''));
  }

  #[Group('units')]
  public function testGetStreamUrlPatternMatchesValidRtspUrl(): void
  {
    $pattern = StreamProtocol::getStreamUrlPattern('rtsp');

    static::assertIsString($pattern);
    static::assertSame(1, preg_match($pattern, 'rtsp://cam.local:554/live'));
  }

  #[Group('units')]
  public function testGetStreamUrlPatternRejectsUrlOfAnotherProtocol(): void
  {
    $pattern = StreamProtocol::getStreamUrlPattern('rtsps');

    static::assertIsString($pattern);
    static::assertSame(0, preg_match($pattern, 'rtsp://cam.local:554/live'));
    static::assertSame(1, preg_match($pattern, 'rtsps://cam.local:322/live'));
  }

  #[Group('units')]
  public function testGetStreamUrlPatternIsAnchored(): void
  {
    $pattern = StreamProtocol::getStreamUrlPattern('rtsp');

    static::assertIsString($pattern);
    static::assertSame(0, preg_match($pattern, 'xrtsp://cam.local/live'));
    static::assertSame(0, preg_match($pattern, 'rtsp://cam.local/live trailing'));
  }

  #[Group('units')]
  public function testGetStreamUrlPatternsListsRtspAndRtspsSchemes(): void
  {
    $patterns = StreamProtocol::getStreamUrlPatterns();

    static::assertStringContainsString('[rR][tT][sS][pP]', $patterns);
    static::assertStringContainsString('[rR][tT][sS][pP][sS]', $patterns);
  }
}
