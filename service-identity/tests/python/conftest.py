import pytest
import os

@pytest.fixture
def base_url():
    return os.getenv("API_BASE_URL", "http://localhost:8080/api/identity/api")
