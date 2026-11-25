<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Media;

use App\Framework\Exceptions\ModuleException;

final class StreamProtocol
{
  private const HOST_REGEX = '([^\/\s:@]+|\[[0-9a-fA-F:]+\])';
  private const PORT_REGEX = '\d+';
  private const PATH_REGEX = '[^\s]+';

  private const array PATTERNS = [
    'rtsp' => '<host>(:<port>)?(\/<path>)?',
    'rtsps' => '<host>(:<port>)?(\/<path>)?',
  ];

  public static function isSupported(string $url): bool
  {
    $parsed = parse_url($url);
    if ($parsed === false) {
      throw new \InvalidArgumentException('Invalid URL');
    }

    $protocol = strtolower($parsed['scheme'] ?? '');
    if ($protocol === '') {
      throw new \InvalidArgumentException('URL must have a scheme');
    }

    return array_key_exists($protocol, self::PATTERNS);
  }

  public static function isValidStreamUrl(string $url): bool
  {
    if (self::isSupported($url) === false) {
      return false;
    }

    $parsed = parse_url($url);

    if ($parsed === false) {
      throw new \InvalidArgumentException('Invalid URL');
    }

    $protocol = strtolower($parsed['scheme'] ?? '');

    if (array_key_exists('user', $parsed) || array_key_exists('pass', $parsed)) {
      return false;
    }

    $pattern = self::getStreamUrlPattern($protocol);

    if ($pattern === null) {
      return false;
    }
    
    return preg_match($pattern, $url) === 1;
  }

  public static function getStreamUrlPattern(string $protocol): ?string
  {

    if (!array_key_exists($protocol, self::PATTERNS)) {
      return null;
    }

    $pattern = self::parseProtocolPattern($protocol);

    return  '/^' . $pattern . '$/';
  }

  public static function getStreamUrlPatterns(): string
  {
    $patterns = [];
    foreach (self::PATTERNS as $protocol => $pattern) {
      $patterns[] = self::parseProtocolPattern($protocol);
    }

    $r = '/^(' . join(')|(', $patterns) . ')$/';
    $r = $r;

    return $r; 
  }

  private static function parseProtocolPattern(string $protocol): string
  {
    if (!array_key_exists($protocol, self::PATTERNS)) {
      throw new ModuleException("Unsupported protocol: $protocol");
    }

    $raw_pattern = self::PATTERNS[$protocol];
    $pattern = self::toCaseInsensitiveRegex($protocol) . ':\/\/';
    $pattern .= str_replace(['<host>', '<port>', '<path>'], [self::HOST_REGEX, self::PORT_REGEX, self::PATH_REGEX], $raw_pattern);

    return $pattern;
  }

  private static function toCaseInsensitiveRegex(string $string): string
  {
    $pattern = '';
    for ($i = 0; $i < strlen($string); $i++) {
      $char = $string[$i];
      if (ctype_alpha($char)) {
        $pattern .= '[' . strtolower($char) . strtoupper($char) . ']';
      } else {
        $pattern .= preg_quote($char, '/');
      }
    }
    return $pattern;
  }
}
