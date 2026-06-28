from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)


def test_health_endpoint_returns_ok():
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_analyze_quote_returns_structured_analysis():
    payload = {
        "quote_id": 1,
        "vendor_name": "Berlin Office Supplies GmbH",
        "total_amount": 12500.00,
        "currency": "EUR",
        "delivery_days": 45,
        "payment_terms": "50% advance, net 7 days",
        "warranty_months": 6,
        "items": [
            {
                "description": "Ergonomic office chair",
                "quantity": 10,
                "unit_price": 1250,
                "total": 12500
            }
        ]
    }

    response = client.post("/analyze-quote", json=payload)

    assert response.status_code == 200

    data = response.json()

    assert data["quote_id"] == 1
    assert data["hidden_costs_detected"] is True
    assert data["risk_level"] == "high"
    assert data["confidence_score"] > 0
    assert "recommendation" in data
    assert len(data["hidden_costs_notes"]) >= 2


def test_analyze_quote_validates_negative_total_amount():
    payload = {
        "quote_id": 1,
        "vendor_name": "Invalid Vendor",
        "total_amount": -100,
        "currency": "EUR",
        "items": []
    }

    response = client.post("/analyze-quote", json=payload)

    assert response.status_code == 422
