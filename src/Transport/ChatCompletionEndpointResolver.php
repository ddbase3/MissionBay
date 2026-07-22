<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Transport;

/**
 * Resolves a configured connection URL and chat-completions path exactly once.
 *
 * Connections may contain an API base URL or an already complete endpoint.
 * Query-bearing endpoints are treated as complete because their query often
 * carries provider-specific routing information.
 */
final class ChatCompletionEndpointResolver {

	public static function resolve(string $endpoint, string $path = '/v1/chat/completions'): string {
		$endpoint = trim($endpoint);
		$path = trim($path);

		if ($path !== '' && preg_match('#^https?://#i', $path) === 1) {
			return $path;
		}
		if ($endpoint === '') {
			throw new \RuntimeException('Missing chat-completions endpoint.');
		}
		if ($path === '') {
			return $endpoint;
		}

		$endpointQuery = parse_url($endpoint, PHP_URL_QUERY);
		if ($endpointQuery !== null && $endpointQuery !== false && $endpointQuery !== '') {
			return $endpoint;
		}

		$normalizedPath = '/' . ltrim($path, '/');
		$endpointPath = (string)(parse_url($endpoint, PHP_URL_PATH) ?? '');

		if ($endpointPath !== '') {
			$normalizedEndpointPath = '/' . trim($endpointPath, '/');

			if (rtrim($normalizedEndpointPath, '/') === rtrim($normalizedPath, '/')) {
				return $endpoint;
			}
			if (
				$normalizedEndpointPath !== '/'
				&& str_ends_with(rtrim($normalizedEndpointPath, '/'), rtrim($normalizedPath, '/'))
			) {
				return $endpoint;
			}
			if (
				$normalizedEndpointPath !== '/'
				&& str_starts_with($normalizedPath . '/', rtrim($normalizedEndpointPath, '/') . '/')
			) {
				$suffix = substr($normalizedPath, strlen(rtrim($normalizedEndpointPath, '/')));

				if ($suffix === false || $suffix === '') {
					return $endpoint;
				}

				$normalizedPath = '/' . ltrim($suffix, '/');
			}
		}

		return rtrim($endpoint, '/') . $normalizedPath;
	}
}
