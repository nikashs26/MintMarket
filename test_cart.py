import requests
import json
import sys

BASE_URL = "http://localhost:8001"
SESSION = requests.Session()

def test_cart_flow():
    print(f"Testing Cart Flow on {BASE_URL}...")
    
    # 1. Register/Login
    username = "testuser_" + str(hash("salt"))[:5]
    password = "password123"
    
    # Try login first, if fail then register
    # Assuming api/auth.php exists and handles JSON login
    print(f"Attempting to login/register as {username}...")
    
    # Register
    reg_payload = {"username": username, "email": f"{username}@example.com", "password": password}
    SESSION.post(f"{BASE_URL}/api/auth.php?action=register", json=reg_payload)
    
    # Login
    login_payload = {"username": username, "password": password}
    resp = SESSION.post(f"{BASE_URL}/api/auth.php?action=login", json=login_payload)
    
    if resp.status_code != 200:
        print(f"Login failed: {resp.text}")
        # Try to continue anyway if session was set?
    
    print("Login response:", resp.text)
    
    # 2. Add Item to Cart
    # We need a valid listing ID. Let's assume ID 1 exists or use one from DB?
    # I'll try adding ID 7 (from previous user request)
    product_id = 7
    print(f"Adding product {product_id} to cart...")
    
    resp = SESSION.post(f"{BASE_URL}/cart_functionalities.php", data={
        "action": "add",
        "product_ID": product_id,
        "quantity": 1
    })
    
    print("Add to cart response:", resp.text)
    
    # 3. Load Cart
    print("Loading cart...")
    resp = SESSION.get(f"{BASE_URL}/cart_functionalities.php?action=load")
    
    try:
        data = resp.json()
        print("Cart Data:", json.dumps(data, indent=2))
        
        if data.get('totalItemCount', 0) > 0:
            print("SUCCESS: Item found in cart.")
        else:
            print("FAILURE: Cart is empty after adding item.")
            sys.exit(1)
            
        if data.get('subtotal', 0) > 0:
            print("SUCCESS: Subtotal is calculated.")
        else:
            print("FAILURE: Subtotal is 0.")
            sys.exit(1)
            
    except json.JSONDecodeError:
        print("FAILURE: Could not parse JSON from load endpoint.")
        print("Raw response:", resp.text)
        sys.exit(1)

if __name__ == "__main__":
    try:
        test_cart_flow()
        print("\nALL TESTS PASSED.")
    except Exception as e:
        print(f"\nTEST FAILED: {e}")
        sys.exit(1)
