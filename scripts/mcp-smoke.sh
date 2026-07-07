#!/usr/bin/env bash
set -u

URL="${1:-}"
TOKEN="${2:-}"
PROFILE_ID="${3:-}"
REQUIRE_ILIAS_CONTEXT="${MCP_REQUIRE_ILIAS_CONTEXT:-0}"

if [ "$PROFILE_ID" = "--require-ilias-context" ]; then
	PROFILE_ID=""
	REQUIRE_ILIAS_CONTEXT="1"
fi

if [ "${4:-}" = "--require-ilias-context" ]; then
	REQUIRE_ILIAS_CONTEXT="1"
fi

if [ -z "$PROFILE_ID" ]; then
	PROFILE_ID="$(python3 - "$URL" <<'PYURL'
from urllib.parse import urlparse, parse_qs
import sys
query = parse_qs(urlparse(sys.argv[1]).query)
print((query.get('profile') or [''])[0])
PYURL
)"
fi

if [ -z "$URL" ] || [ -z "$TOKEN" ]; then
	echo "Usage: $0 <mcp-url> <bearer-token>"
	exit 2
fi

TMP_DIR="$(mktemp -d)"
FAILURES=0

cleanup() {
	rm -rf "$TMP_DIR"
}
trap cleanup EXIT

json_value() {
	python3 - "$1" "$2" <<'PY'
import json
import sys

path = sys.argv[1]
expr = sys.argv[2]

try:
	with open(path, 'r', encoding='utf-8') as f:
		data = json.load(f)
except Exception as e:
	print(f'__JSON_ERROR__:{e}')
	sys.exit(0)

cur = data
for part in expr.split('.'):
	if part == '':
		continue
	if isinstance(cur, list):
		try:
			cur = cur[int(part)]
		except Exception:
			print('')
			sys.exit(0)
	elif isinstance(cur, dict):
		cur = cur.get(part, '')
	else:
		print('')
		sys.exit(0)

if isinstance(cur, (dict, list)):
	print(json.dumps(cur, ensure_ascii=False))
elif cur is True:
	print('true')
elif cur is False:
	print('false')
elif cur is None:
	print('null')
else:
	print(str(cur))
PY
}

run_request() {
	local name="$1"
	local method="$2"
	local token="$3"
	local accept="$4"
	local protocol="$5"
	local origin="$6"
	local body="$7"
	local header_file="$TMP_DIR/$name.headers"
	local body_file="$TMP_DIR/$name.body"
	local status_file="$TMP_DIR/$name.status"

	local args=(
		-sS
		-o "$body_file"
		-D "$header_file"
		-w '%{http_code}'
		-X "$method"
		"$URL"
		-H "Authorization: Bearer $token"
		-H 'Content-Type: application/json'
	)

	if [ -n "$accept" ]; then
		args+=( -H "Accept: $accept" )
	fi

	if [ -n "$protocol" ]; then
		args+=( -H "MCP-Protocol-Version: $protocol" )
	fi

	if [ -n "$origin" ]; then
		args+=( -H "Origin: $origin" )
	fi

	if [ "$method" != "GET" ]; then
		args+=( --data-binary "$body" )
	fi

	curl "${args[@]}" > "$status_file"
}

assert_status() {
	local name="$1"
	local expected="$2"
	local actual
	actual="$(cat "$TMP_DIR/$name.status")"

	if [ "$actual" != "$expected" ]; then
		echo "FAIL $name: expected HTTP $expected, got $actual"
		print_debug "$name"
		FAILURES=$((FAILURES + 1))
		return 1
	fi

	echo "OK   $name: HTTP $actual"
	return 0
}

assert_json_value() {
	local name="$1"
	local expr="$2"
	local expected="$3"
	local actual
	actual="$(json_value "$TMP_DIR/$name.body" "$expr")"

	if [ "$actual" != "$expected" ]; then
		echo "FAIL $name: expected $expr=$expected, got $actual"
		print_debug "$name"
		FAILURES=$((FAILURES + 1))
		return 1
	fi

	echo "OK   $name: $expr=$actual"
	return 0
}

assert_body_empty() {
	local name="$1"

	if [ -s "$TMP_DIR/$name.body" ]; then
		echo "FAIL $name: expected empty body"
		print_debug "$name"
		FAILURES=$((FAILURES + 1))
		return 1
	fi

	echo "OK   $name: empty body"
	return 0
}

assert_body_contains() {
	local name="$1"
	local needle="$2"

	if ! grep -q "$needle" "$TMP_DIR/$name.body"; then
		echo "FAIL $name: body does not contain $needle"
		print_debug "$name"
		FAILURES=$((FAILURES + 1))
		return 1
	fi

	echo "OK   $name: body contains $needle"
	return 0
}

print_debug() {
	local name="$1"
	echo "--- $name headers ---"
	cat "$TMP_DIR/$name.headers" || true
	echo "--- $name body ---"
	cat "$TMP_DIR/$name.body" || true
	echo
}

COMMON_ACCEPT='application/json, text/event-stream'

run_request initialize POST "$TOKEN" "$COMMON_ACCEPT" '' '' '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"mcp-smoke","version":"1"}}}'
assert_status initialize 200
assert_json_value initialize result.protocolVersion '2025-06-18'
assert_json_value initialize result.serverInfo.version '1.0.0'

run_request initialized POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","method":"notifications/initialized","params":{}}'
assert_status initialized 202
assert_body_empty initialized

run_request cancelled POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","method":"notifications/cancelled","params":{"requestId":"smoke-cancel","reason":"smoke test"}}'
assert_status cancelled 202
assert_body_empty cancelled

run_request ping POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":19,"method":"ping","params":{}}'
assert_status ping 200
assert_json_value ping jsonrpc '2.0'
assert_json_value ping result '{}'

run_request tools_list POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
assert_status tools_list 200
assert_json_value tools_list jsonrpc '2.0'
assert_json_value tools_list result.tools.0.name 'missionbay_confirm_action'
assert_json_value tools_list result.tools.0.annotations.destructiveHint 'true'
assert_json_value tools_list result.tools.0.outputSchema.type 'object'

run_request tools_list_cursor POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":21,"method":"tools/list","params":{"cursor":"0"}}'
assert_status tools_list_cursor 200
assert_json_value tools_list_cursor jsonrpc '2.0'
assert_body_contains tools_list_cursor 'missionbay_confirm_action'

run_request invalid_tools_cursor POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":22,"method":"tools/list","params":{"cursor":"not-a-cursor"}}'
assert_status invalid_tools_cursor 200
assert_json_value invalid_tools_cursor error.code '-32602'
assert_body_contains invalid_tools_cursor 'Invalid tools/list cursor'

run_request general_info POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"general_info","arguments":{"topic":"topics","scope":"summary","limit":1}}}'
assert_status general_info 200
assert_json_value general_info jsonrpc '2.0'
assert_json_value general_info result.content.0.type 'text'

run_request empty_batch POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '[]'
assert_status empty_batch 400
assert_json_value empty_batch error.code '-32600'
assert_body_contains empty_batch 'empty batch'

run_request missing_confirmation POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":15,"method":"tools/call","params":{"name":"missionbay_confirm_action","arguments":{"confirmation_id":"mcp-cnf-does-not-exist","decision":"accept"}}}'
assert_status missing_confirmation 200
assert_json_value missing_confirmation result.isError 'true'
assert_body_contains missing_confirmation 'Confirmation not found'

run_request resources_list POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":10,"method":"resources/list","params":{}}'
assert_status resources_list 200
assert_json_value resources_list jsonrpc '2.0'
assert_body_contains resources_list 'generalinfo://topics'

run_request resource_templates_list POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":17,"method":"resources/templates/list","params":{}}'
assert_status resource_templates_list 200
assert_json_value resource_templates_list jsonrpc '2.0'
assert_body_contains resource_templates_list 'generalinfo://topic/{topic}'

run_request resource_topics POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":11,"method":"resources/read","params":{"uri":"generalinfo://topics"}}'
assert_status resource_topics 200
assert_json_value resource_topics result.contents.0.uri 'generalinfo://topics'

if [ -n "$PROFILE_ID" ]; then
	run_request resource_profile POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' "{\"jsonrpc\":\"2.0\",\"id\":12,\"method\":\"resources/read\",\"params\":{\"uri\":\"missionbay://profile/$PROFILE_ID\"}}"
	assert_status resource_profile 200
	assert_json_value resource_profile result.contents.0.uri "missionbay://profile/$PROFILE_ID"
fi


if grep -q 'ilias://context/current' "$TMP_DIR/resources_list.body"; then
	run_request resource_ilias_context POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":16,"method":"resources/read","params":{"uri":"ilias://context/current"}}'
	assert_status resource_ilias_context 200
	assert_json_value resource_ilias_context result.contents.0.uri 'ilias://context/current'
else
	if [ "$REQUIRE_ILIAS_CONTEXT" = "1" ]; then
		echo "FAIL resource_ilias_context: ilias://context/current not advertised"
		print_debug resources_list
		FAILURES=$((FAILURES + 1))
	else
		echo "SKIP resource_ilias_context: ilias://context/current not advertised"
	fi
fi


run_request prompts_list POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":13,"method":"prompts/list","params":{}}'
assert_status prompts_list 200
assert_json_value prompts_list jsonrpc '2.0'
assert_body_contains prompts_list 'generalinfo_lookup'

run_request prompts_list_cursor POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":18,"method":"prompts/list","params":{"cursor":"0"}}'
assert_status prompts_list_cursor 200
assert_json_value prompts_list_cursor jsonrpc '2.0'
assert_body_contains prompts_list_cursor 'generalinfo_lookup'

run_request prompt_generalinfo POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":14,"method":"prompts/get","params":{"name":"generalinfo_lookup","arguments":{"topic":"topics","scope":"summary"}}}'
assert_status prompt_generalinfo 200
assert_json_value prompt_generalinfo result.messages.0.role 'user'
assert_json_value prompt_generalinfo result.messages.0.content.type 'text'


run_request wrong_token POST 'wrong-token' "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":4,"method":"tools/list","params":{}}'
assert_status wrong_token 401
assert_json_value wrong_token error.message 'Unauthorized'

run_request unknown_method POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":5,"method":"does/not/exist","params":{}}'
assert_status unknown_method 200
assert_json_value unknown_method error.code '-32601'

run_request unknown_tool POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' '{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"does_not_exist","arguments":{}}}'
assert_status unknown_tool 200
assert_json_value unknown_tool result.isError 'true'

run_request get_rejected GET "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' '' ''
assert_status get_rejected 405
assert_body_contains get_rejected 'Method not allowed'

run_request invalid_protocol POST "$TOKEN" "$COMMON_ACCEPT" '1900-01-01' '' '{"jsonrpc":"2.0","id":7,"method":"tools/list","params":{}}'
assert_status invalid_protocol 400
assert_body_contains invalid_protocol 'Unsupported MCP-Protocol-Version'

run_request invalid_accept POST "$TOKEN" 'text/html' '2025-06-18' '' '{"jsonrpc":"2.0","id":8,"method":"tools/list","params":{}}'
assert_status invalid_accept 406
assert_body_contains invalid_accept 'Not acceptable'

run_request foreign_origin POST "$TOKEN" "$COMMON_ACCEPT" '2025-06-18' 'https://evil.example' '{"jsonrpc":"2.0","id":9,"method":"tools/list","params":{}}'
assert_status foreign_origin 403
assert_body_contains foreign_origin 'Forbidden Origin'

if [ "$FAILURES" -gt 0 ]; then
	echo "MCP smoke test failed: $FAILURES failure(s)."
	exit 1
fi

echo "MCP smoke test passed."
