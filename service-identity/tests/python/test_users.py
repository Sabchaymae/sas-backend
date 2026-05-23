import pytest
import requests
from faker import Faker

fake = Faker()

@pytest.fixture
def new_user_data():
    password = "Password123!"
    return {
        "nom": fake.last_name(),
        "prenom": fake.first_name(),
        "email": fake.email(),
        "password": password,
        "role": "collaborateur",
        "telephone": fake.phone_number()[:20],
        "cin": fake.bothify(text='??######').upper(),
        "adresse": fake.address()
    }

def test_list_users(base_url):
    response = requests.get(f"{base_url}/users")
    assert response.status_code == 200
    assert isinstance(response.json().get('data'), list)

def test_create_user(base_url, new_user_data):
    response = requests.post(f"{base_url}/users", json=new_user_data)
    assert response.status_code == 201
    data = response.json().get('data')
    assert data['email'] == new_user_data['email']
    assert data['nom'] == new_user_data['nom']

def test_get_user(base_url, new_user_data):
    # First create
    create_res = requests.post(f"{base_url}/users", json=new_user_data)
    user_id = create_res.json()['data']['id']
    
    # Then get
    response = requests.get(f"{base_url}/users/{user_id}")
    assert response.status_code == 200
    assert response.json()['data']['id'] == user_id

def test_update_user(base_url, new_user_data):
    # First create
    create_res = requests.post(f"{base_url}/users", json=new_user_data)
    user_id = create_res.json()['data']['id']
    
    # Then update
    updated_data = {
        "nom": "UpdatedNom",
        "prenom": "UpdatedPrenom",
        "email": f"updated_{fake.email()}",
        "role": "admin"
    }
    response = requests.put(f"{base_url}/users/{user_id}", json=updated_data)
    assert response.status_code == 200
    assert response.json()['data']['nom'] == "UpdatedNom"

def test_delete_user(base_url, new_user_data):
    # First create
    create_res = requests.post(f"{base_url}/users", json=new_user_data)
    user_id = create_res.json()['data']['id']
    
    # Then delete
    response = requests.delete(f"{base_url}/users/{user_id}")
    assert response.status_code == 204
    
    # Verify it's gone
    get_res = requests.get(f"{base_url}/users/{user_id}")
    assert get_res.status_code == 404
