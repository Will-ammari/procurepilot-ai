from pydantic import BaseModel, Field
from typing import List, Optional


class QuoteItem(BaseModel):
    description: str
    quantity: float = Field(gt=0)
    unit_price: float = Field(ge=0)
    total: float = Field(ge=0)


class AnalyzeQuoteRequest(BaseModel):
    quote_id: int
    vendor_name: str
    total_amount: float = Field(ge=0)
    currency: str = "EUR"
    delivery_days: Optional[int] = Field(default=None, ge=0)
    payment_terms: Optional[str] = None
    warranty_months: Optional[int] = Field(default=None, ge=0)
    items: List[QuoteItem] = Field(default_factory=list)


class AnalyzeQuoteResponse(BaseModel):
    quote_id: int
    summary: str
    hidden_costs_detected: bool
    hidden_costs_notes: List[str]
    risk_level: str
    confidence_score: float
    recommendation: str