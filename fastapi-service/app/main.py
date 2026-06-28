from fastapi import FastAPI
from app.schemas import AnalyzeQuoteRequest, AnalyzeQuoteResponse
from app.services.quote_analyzer import QuoteAnalyzerService

app = FastAPI(
    title="ProcurePilot AI Service",
    description="FastAPI microservice for quote analysis in ProcurePilot AI.",
    version="1.0.0",
)

quote_analyzer = QuoteAnalyzerService()


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/analyze-quote", response_model=AnalyzeQuoteResponse)
def analyze_quote(payload: AnalyzeQuoteRequest) -> AnalyzeQuoteResponse:
    return quote_analyzer.analyze(payload)
