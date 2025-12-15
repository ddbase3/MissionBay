<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;

/**
 * WeatherAgentTool
 *
 * Fetches weather information from the Open-Meteo API (no API key required).
 * Supports:
 * - Current weather
 * - Forecast weather for a specific date
 *
 * Parameters:
 * - location (string, required): City or region name.
 * - date (string, optional): Forecast date (YYYY-MM-DD). If missing, current weather is returned.
 */
class WeatherAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'weatheragenttool';
	}

	public function getDescription(): string {
		return 'Fetches current or forecast weather using the Open-Meteo public API.';
	}

	/**
	 * Tool definition exposed to the LLM.
	 *
	 * @return array[]
	 */
	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Weather Information Lookup',
			'category' => 'lookup',
			'tags' => ['weather', 'forecast', 'location', 'open_meteo'],
			'priority' => 50,
			'function' => [
				'name' => 'get_weather',
				'description' => 'Returns current or forecast weather for a given location.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'location' => [
							'type' => 'string',
							'description' => 'City or place name (e.g. Berlin, New York).'
						],
						'date' => [
							'type' => 'string',
							'description' => 'Optional forecast date (YYYY-MM-DD).'
						]
					],
					'required' => ['location']
				]
			]
		]];
	}

	/**
	 * Executes the tool call.
	 *
	 * @param string $toolName
	 * @param array $arguments
	 * @param IAgentContext $context
	 * @return array<string,mixed>
	 */
	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'get_weather') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		$location = trim($arguments['location'] ?? '');
		$date     = trim($arguments['date'] ?? '');

		if ($location === '') {
			return ['error' => 'Missing required parameter: location'];
		}

		// Step 1: Convert location to lat/long
		$geo = $this->geocodeLocation($location);
		if ($geo === null) {
			return ['error' => 'Could not resolve location: ' . $location];
		}

		// Step 2: Decide between current weather or forecast
		if ($date !== '') {
			$weather = $this->fetchForecast($geo['lat'], $geo['lon'], $date);
		} else {
			$weather = $this->fetchCurrentWeather($geo['lat'], $geo['lon']);
		}

		if ($weather === null) {
			return ['error' => 'Weather data unavailable'];
		}

		return [
			'query' => [
				'location' => $location,
				'date'     => $date !== '' ? $date : null
			],
			'location_resolved' => $geo,
			'weather' => $weather
		];
	}

	/**
	 * Resolve a city name into lat/lon using open-meteo Geocoding API.
	 */
	private function geocodeLocation(string $location): ?array {
		$url = 'https://geocoding-api.open-meteo.com/v1/search?count=1&name=' . urlencode($location);

		$json = @file_get_contents($url);
		if (!$json) {
			return null;
		}

		$data = json_decode($json, true);
		if (!isset($data['results'][0])) {
			return null;
		}

		$hit = $data['results'][0];

		return [
			'name' => $hit['name'] ?? $location,
			'lat'  => $hit['latitude'] ?? null,
			'lon'  => $hit['longitude'] ?? null,
			'country' => $hit['country'] ?? '',
			'timezone' => $hit['timezone'] ?? ''
		];
	}

	/**
	 * Fetch current weather from Open-Meteo.
	 */
	private function fetchCurrentWeather(float $lat, float $lon): ?array {
		$url = sprintf(
			'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&current_weather=true',
			$lat,
			$lon
		);

		$json = @file_get_contents($url);
		if (!$json) {
			return null;
		}

		$data = json_decode($json, true);
		return $data['current_weather'] ?? null;
	}

	/**
	 * Fetch forecast for a specific date.
	 * Returns temperature, wind, and weather code if available.
	 */
	private function fetchForecast(float $lat, float $lon, string $date): ?array {
		$url = sprintf(
			'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&daily=temperature_2m_max,temperature_2m_min,weathercode,windspeed_10m_max&timezone=auto&start_date=%s&end_date=%s',
			$lat,
			$lon,
			$date,
			$date
		);

		$json = @file_get_contents($url);
		if (!$json) {
			return null;
		}

		$data = json_decode($json, true);

		if (!isset($data['daily'])) {
			return null;
		}

		return [
			'date' => $date,
			'temperature_max' => $data['daily']['temperature_2m_max'][0] ?? null,
			'temperature_min' => $data['daily']['temperature_2m_min'][0] ?? null,
			'weathercode'     => $data['daily']['weathercode'][0] ?? null,
			'windspeed_max'   => $data['daily']['windspeed_10m_max'][0] ?? null
		];
	}
}
