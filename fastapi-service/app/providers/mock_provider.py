from app.schemas import AnalyzeQuoteRequest, AnalyzeQuoteResponse


class MockQuoteAnalysisProvider:
    def analyze(self, payload: AnalyzeQuoteRequest) -> AnalyzeQuoteResponse:
        hidden_costs_notes = []

        if payload.delivery_days is not None and payload.delivery_days > 30:
            hidden_costs_notes.append("Long delivery time may create operational delay costs.")

        if payload.payment_terms:
            terms = payload.payment_terms.lower()

            if "advance" in terms or "upfront" in terms or "prepayment" in terms:
                hidden_costs_notes.append("Payment terms require upfront cash commitment.")

            if "net 7" in terms or "7 days" in terms:
                hidden_costs_notes.append("Short payment window may affect cash flow.")

        if payload.warranty_months is not None and payload.warranty_months < 12:
            hidden_costs_notes.append("Warranty period is shorter than typical procurement expectations.")

        hidden_costs_detected = len(hidden_costs_notes) > 0

        risk_level = "low"
        if len(hidden_costs_notes) == 1:
            risk_level = "medium"
        elif len(hidden_costs_notes) >= 2:
            risk_level = "high"

        confidence_score = 0.86
        if payload.total_amount > 10000:
            confidence_score = 0.82

        recommendation = "Quote looks acceptable for further procurement review."
        if risk_level == "medium":
            recommendation = "Quote requires procurement review before approval."
        elif risk_level == "high":
            recommendation = "Quote should be reviewed carefully due to multiple risk indicators."

        summary = (
            f"Quote from {payload.vendor_name} totals "
            f"{payload.total_amount:.2f} {payload.currency}. "
            f"Detected risk level: {risk_level}."
        )

        return AnalyzeQuoteResponse(
            quote_id=payload.quote_id,
            summary=summary,
            hidden_costs_detected=hidden_costs_detected,
            hidden_costs_notes=hidden_costs_notes,
            risk_level=risk_level,
            confidence_score=confidence_score,
            recommendation=recommendation,
        )
