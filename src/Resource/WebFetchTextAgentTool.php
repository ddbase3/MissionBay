<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;

final class WebFetchTextAgentTool extends AbstractAgentResource implements IAgentTool {
	private int $maxBytes = 262144; // 256KB
	private int $timeoutSeconds = 12;
	private int $connectTimeoutSeconds = 5;
	private int $maxRedirects = 5;

	public static function getName(): string { return 'webfetchtextagenttool'; }

	public function getDescription(): string {
		return 'Fetches a webpage and extracts title, meta description, text and raw HTML.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Webpage Fetch (Text)',
			'category' => 'web',
			'tags' => ['web', 'fetch', 'http', 'html', 'text'],
			'priority' => 50,
			'function' => [
				'name' => 'web_fetch_text',
				'description' => 'Fetches a webpage (GET) and extracts text + metadata.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'url' => ['type' => 'string', 'description' => 'URL to fetch.'],
					],
					'required' => ['url'],
				],
			],
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'web_fetch_text') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		// One-shot shutdown trap for fatal errors inside this request.
		$fatalTrap = $this->installFatalTrap();

		try {
			$url = trim((string)($arguments['url'] ?? ''));
			if ($url === '') return ['error' => 'Missing parameter: url'];

			$url = $this->normalizeUrl($url);
			if ($url === null) return ['url' => (string)($arguments['url'] ?? ''), 'error' => 'Invalid URL'];

			if (!$this->isSafeUrl($url)) {
				return ['url' => $url, 'error' => 'Blocked internal or unsafe URL'];
			}

			$fetch = $this->safeFetchCurl($url);
			if (!$fetch['ok']) {
				return [
					'url' => $url,
					'effective_url' => $fetch['effective_url'] ?? null,
					'status' => $fetch['status'] ?? null,
					'error' => $fetch['error'] ?? 'Unknown fetch error',
				];
			}

			$html = $fetch['body'] ?? '';
			$html = $this->coerceUtf8($html);

			return [
				'url' => $url,
				'effective_url' => $fetch['effective_url'] ?? $url,
				'status' => $fetch['status'] ?? null,
				'content_type' => $fetch['content_type'] ?? null,
				'title' => $this->extractTitle($html),
				'description' => $this->extractMetaDescription($html),
				'text' => $this->htmlToText($html),
				'raw_html' => $html,
			];
		} catch (\Throwable $e) {
			// Catch literally anything throwable
			return [
				'error' => 'Unhandled exception: ' . $e->getMessage(),
				'exception_class' => get_class($e),
			];
		} finally {
			// If a fatal occurred, shutdown handler stores it in $fatalTrap['fatal'].
			// We can’t “continue” after a fatal, but in many cases this still helps you see it
			// when the request ends (e.g. your agent runtime might collect it).
			$fatalTrap['restore']();
		}
	}

	// ------------------------------
	// FATAL TRAP
	// ------------------------------
	private function installFatalTrap(): array {
		// Convert warnings/notices to exceptions locally (prevents “random warnings” from breaking JSON)
		$prevErrHandler = set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
			// Respect @-suppression
			if ((error_reporting() & $errno) === 0) return true;
			throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$prevExHandler = set_exception_handler(function (\Throwable $e) {
			// Last resort: log to PHP error log (if it exists). Don’t echo (would break JSON)
			error_log('Top-level exception: ' . $e::class . ': ' . $e->getMessage());
		});

		$shutdown = function () {
			$last = error_get_last();
			if (!$last) return;

			$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
			if (in_array($last['type'] ?? 0, $fatalTypes, true)) {
				error_log('FATAL: ' . ($last['message'] ?? 'unknown') . ' in ' . ($last['file'] ?? '?') . ':' . ($last['line'] ?? 0));
			}
		};
		register_shutdown_function($shutdown);

		return [
			'restore' => function () use ($prevErrHandler, $prevExHandler) {
				restore_error_handler();
				if ($prevExHandler !== null) {
					set_exception_handler($prevExHandler);
				} else {
					restore_exception_handler();
				}
			}
		];
	}

	// ------------------------------
	// FETCH (cURL, size-capped)
	// ------------------------------
	private function safeFetchCurl(string $url): array {
		if (!extension_loaded('curl')) {
			return ['ok' => false, 'error' => 'cURL extension not available'];
		}

		$ch = curl_init($url);
		if ($ch === false) return ['ok' => false, 'error' => 'Failed to init cURL'];

		$max = $this->maxBytes;
		$body = '';

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => $this->maxRedirects,
			CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
			CURLOPT_TIMEOUT        => $this->timeoutSeconds,
			CURLOPT_USERAGENT      => 'MissionBayWebFetch/3.0',
			CURLOPT_HTTPHEADER     => [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en,de;q=0.8,*;q=0.5',
				'Accept-Encoding: identity',
				'Connection: close',
			],
			CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_SSL_VERIFYPEER  => true,
			CURLOPT_SSL_VERIFYHOST  => 2,
			CURLOPT_WRITEFUNCTION   => function ($ch, string $chunk) use (&$body, $max): int {
				$remaining = $max - strlen($body);
				if ($remaining <= 0) return 0; // abort: cap reached
				$body .= (strlen($chunk) > $remaining) ? substr($chunk, 0, $remaining) : $chunk;
				return strlen($chunk);
			},
		]);

		$ok = curl_exec($ch);
		$err = curl_error($ch) ?: null;

		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: null;
		$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
		$effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;

		curl_close($ch);

		// If we hit cap, curl_exec can be false. If we have body, treat as OK.
		if ($ok === false && $body === '') {
			return ['ok' => false, 'error' => $err ?: 'Unknown cURL error', 'status' => $status, 'effective_url' => $effective];
		}

		if (!$this->isSafeUrl($effective)) {
			return ['ok' => false, 'error' => 'Blocked internal or unsafe URL after redirect', 'status' => $status, 'effective_url' => $effective];
		}

		return [
			'ok' => true,
			'error' => null,
			'status' => $status,
			'content_type' => $type,
			'effective_url' => $effective,
			'body' => $body,
		];
	}

	// ------------------------------
	// URL SAFETY
	// ------------------------------
	private function normalizeUrl(string $url): ?string {
		$url = trim($url);
		if (!preg_match('~^https?://~i', $url)) return null;

		$p = @parse_url($url);
		if (!is_array($p) || empty($p['host'])) return null;

		// Block user/pass tricks
		if (isset($p['user']) || isset($p['pass'])) return null;

		return $url;
	}

	private function isSafeUrl(string $url): bool {
		$p = @parse_url($url);
		if (!is_array($p)) return false;

		$scheme = strtolower((string)($p['scheme'] ?? ''));
		if (!in_array($scheme, ['http', 'https'], true)) return false;

		$host = strtolower((string)($p['host'] ?? ''));
		if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) return false;

		// If host is IP: block private/reserved
		if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? false : true;
		}
		if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			// Block common local ranges
			if ($host === '::1') return false;
			if (str_starts_with($host, 'fc') || str_starts_with($host, 'fd')) return false; // ULA
			if (str_starts_with($host, 'fe8') || str_starts_with($host, 'fe9') || str_starts_with($host, 'fea') || str_starts_with($host, 'feb')) return false; // link-local
		}

		// DNS resolution check (basic SSRF hardening)
		$ips = @gethostbynamel($host);
		if (is_array($ips)) {
			foreach ($ips as $ip) {
				if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return false;
				}
			}
		}
		return true;
	}

	// ------------------------------
	// HTML EXTRACTION
	// ------------------------------
	private function extractTitle(string $html): ?string {
		return preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)
			? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
			: null;
	}

	private function extractMetaDescription(string $html): ?string {
		// Works even if attributes are in different order / single quotes
		if (preg_match('/<meta\b[^>]*\bname\s*=\s*(["\'])description\1[^>]*>/is', $html, $m0)) {
			if (preg_match('/\bcontent\s*=\s*(["\'])(.*?)\1/is', $m0[0], $m1)) {
				return trim(html_entity_decode($m1[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			}
		}
		return null;
	}

	private function htmlToText(string $html): string {
		$clean = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#is', '', $html) ?? $html;
		$clean = preg_replace('/<!--.*?-->/s', '', $clean) ?? $clean;
		$clean = preg_replace('#</(p|div|h1|h2|h3|h4|h5|h6|li|tr|br|section|article)>#i', "\n", $clean) ?? $clean;

		$clean = strip_tags($clean);
		$clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$clean = preg_replace("/[ \t]+/", " ", $clean) ?? $clean;
		$clean = preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean;
		return trim($clean);
	}

	private function coerceUtf8(string $s): string {
		$fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
		if (is_string($fixed) && $fixed !== '') return $fixed;

		$fixed2 = @mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
		return is_string($fixed2) ? $fixed2 : $s;
	}
}
