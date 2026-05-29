import requests
import time
import sys

BASE_URL = "http://127.0.0.1:8080/api/identity/api/v1/roles-permissions"

def check_endpoint(name, method, url, data=None):
    try:
        if method == "GET":
            response = requests.get(url, params=data, timeout=10)
        else:
            response = requests.post(url, json=data, timeout=10)
        
        if response.status_code == 200:
            print(f"[OK] {name}: SUCCESS (200)")
            return True
        else:
            print(f"[FAIL] {name}: FAILED ({response.status_code}) - {response.text[:100]}")
            return False
    except Exception as e:
        print(f"[ERROR] {name}: ERROR - {str(e)}")
        return False

def run_suite():
    print("\n--- Testing API Suite ---")
    results = []
    
    # 1. List Roles
    results.append(check_endpoint("List Roles", "GET", f"{BASE_URL}/roles"))
    
    # 2. Create Role
    test_role = {"name": f"TestRole_{int(time.time())}", "color": "bg-blue-500"}
    results.append(check_endpoint("Create Role", "POST", f"{BASE_URL}/roles", test_role))
    
    # 3. List Users for Role 1
    results.append(check_endpoint("Role Users", "GET", f"{BASE_URL}/roles/1/users"))
    
    # 4. Permissions Matrix
    results.append(check_endpoint("Permissions Matrix", "GET", f"{BASE_URL}/permissions", {"role_id": 1}))

    return all(results)

if __name__ == "__main__":
    print("Starting API Checker...")
    while True:
        if run_suite():
            print("\nALL SYSTEMS GO! No errors detected.")
            break
        else:
            print("\nErrors detected. Retrying in 5 seconds...")
            time.sleep(5)
