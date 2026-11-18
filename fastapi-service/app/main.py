import logging
import re
import secrets
import time

from fastapi import FastAPI, Request
from app.schemas import AnalyzeQuoteRequest, AnalyzeQuoteResponse
from app.services.quote_analyzer import QuoteAnalyzerService

app = FastAPI(
    title="ProcurePilot AI Service",
    description="FastAPI microservice for quote analysis in ProcurePilot AI.",
    version="1.0.0",
)

logger = logging.getLogger("procurepilot.tracing")
quote_analyzer = QuoteAnalyzerService()
TRACE_PATTERN = re.compile(r"^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$")
REQUEST_ID_PATTERN = re.compile(r"^[A-Za-z0-9._:-]{1,100}$")


@app.middleware("http")
async def trace_http(request: Request, call_next):
    incoming = request.headers.get("traceparent", "")
    match = TRACE_PATTERN.fullmatch(incoming)
    trace_id = (match.group(1) if match and match.group(1) != "0" * 32
                else secrets.token_hex(16))
    span_id = secrets.token_hex(8)
    traceparent = f"00-{trace_id}-{span_id}-01"
    request_id = request.headers.get("x-request-id", "")
    if not REQUEST_ID_PATTERN.fullmatch(request_id):
        request_id = secrets.token_hex(16)

    start = time.perf_counter()
    status = 500
    try:
        response = await call_next(request)
        status = response.status_code
        response.headers["traceparent"] = traceparent
        response.headers["X-Request-Id"] = request_id
        return response
    finally:
        logger.info(
            "ai.http.completed trace_id=%s span_id=%s request_id=%s method=%s "
            "route=%s status=%d duration_ms=%.2f",
            trace_id, span_id, request_id, request.method,
            request.url.path if request.url.path in ("/health", "/analyze-quote") else "other",
            status, (time.perf_counter() - start) * 1000,
        )


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/analyze-quote", response_model=AnalyzeQuoteResponse)
def analyze_quote(payload: AnalyzeQuoteRequest) -> AnalyzeQuoteResponse:
    return quote_analyzer.analyze(payload)
