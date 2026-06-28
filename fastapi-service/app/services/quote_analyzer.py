from app.schemas import AnalyzeQuoteRequest, AnalyzeQuoteResponse
from app.providers.mock_provider import MockQuoteAnalysisProvider


class QuoteAnalyzerService:
    def __init__(self) -> None:
        self.provider = MockQuoteAnalysisProvider()

    def analyze(self, payload: AnalyzeQuoteRequest) -> AnalyzeQuoteResponse:
        return self.provider.analyze(payload)
