<?php declare(strict_types=1);

namespace MissionBay\Content;

use Base3\Api\IOutput;
use Base3\Api\IRequest;

class McpServerTest implements IOutput {

	private IRequest $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	public static function getName(): string {
		return 'mcpservertest';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$text = $this->request->post('text') ?? '';
		$function = $this->request->post('function') ?? '';
		$response = null;
		$error = null;
		$functions = [];

		// MCP-Function-Schema URL
		$getUrl = 'https://agents.base3.de/missionbaymcp.json';
		$getJson = @file_get_contents($getUrl);

		if ($getJson !== false) {
			$data = json_decode($getJson, true);
			if (isset($data['functions']) && is_array($data['functions'])) {
				foreach ($data['functions'] as $entry) {
					if (!isset($entry['name']) && isset($entry['function'])) $entry['name'] = $entry['function'];
					$functions[$entry['name']] = $entry['description'] ?? $entry['name'];
				}
			} else {
				$error = 'Keine Funktionen im JSON gefunden.';
			}
		} else {
			$error = 'Konnte Agentenliste vom Server nicht abrufen.';
		}

		// POST ausführen, wenn gültige Eingabe
		if ($text !== '' && isset($functions[$function])) {
			$postPayload = json_encode([
				'function' => $function,
				'inputs' => ['text' => $text]
			]);

			$options = [
				'http' => [
					'method'  => 'POST',
					'header'  => [
						"Content-Type: application/json",
						"Accept: application/json"
					],
					'content' => $postPayload,
					'timeout' => 5
				]
			];

			$response = @file_get_contents($getUrl, false, stream_context_create($options));
			if ($response === false) {
				$error = 'Fehler beim Aufruf des MCP-Endpunkts.';
			}
		}

		$html = '<h3>MCP Server Test</h3>';
		$html .= '<form method="post">
			<p><label for="function">Funktion wählen:</label><br>
			<select name="function" id="function">';
			foreach ($functions as $key => $desc) {
				$selected = ($key === $function) ? ' selected' : '';
				$html .= '<option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($desc) . '</option>';
			}
			$html .= '</select></p>
			<p><label for="text">Text eingeben:</label><br>
			<input type="text" name="text" id="text" value="' . htmlspecialchars($text) . '" style="width:300px;" /></p>
			<p><input type="submit" value="Agentenfunktion testen"></p>
		</form>';

		if ($error) {
			$html .= '<p style="color:red;"><strong>' . htmlspecialchars($error) . '</strong></p>';
		}

		if ($response !== null) {
			$html .= '<h4>Antwort vom Server:</h4><pre>' . htmlspecialchars($response) . '</pre>';
		}

		return $html;
	}

	public function getHelp(): string {
		return 'Testet den MCP-Endpunkt missionbaymcp.json mit Eingabeformular und dynamischer Funktionsauswahl.';
	}
}
