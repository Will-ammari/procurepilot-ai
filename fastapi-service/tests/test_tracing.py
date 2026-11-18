from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_trace_header_continues_trace_and_creates_new_span():
    trace_id = "0123456789abcdef0123456789abcdef"
    parent = f"00-{trace_id}-1111222233334444-01"
    response = client.get("/health", headers={"traceparent": parent, "X-Request-Id": "case-1"})
    assert response.status_code == 200
    assert response.headers["traceparent"].startswith(f"00-{trace_id}-")
    assert response.headers["traceparent"] != parent
    assert response.headers["x-request-id"] == "case-1"


def test_untrusted_trace_header_is_replaced():
    response = client.get("/health", headers={"traceparent": "not-a-valid-trace-id"})
    assert response.status_code == 200
    assert response.headers["traceparent"].startswith("00-")
    assert len(response.headers["traceparent"]) == 55
