import requests
import sys
import random
from datetime import datetime, timedelta

USE_PROXY = "--proxy" in sys.argv
args = [a for a in sys.argv[1:] if a != "--proxy"]
BASE = args[0] if args else "http://167.172.179.205:8080/api"
RUN_ID = random.randint(1000, 9999)
PROXIES = {"http": "http://127.0.0.1:8080", "https": "http://127.0.0.1:8080"} if USE_PROXY else {}

if USE_PROXY:
    requests.packages.urllib3.disable_warnings()
    print("[*] Proxy mode: routing through 127.0.0.1:8080 (Burp)")


def make_session():
    s = requests.Session()
    if USE_PROXY:
        s.proxies = PROXIES
        s.verify = False
    return s


def api(session, endpoint, data=None):
    url = f"{BASE}/{endpoint}"
    headers = {}

    csrf = getattr(session, '_csrf', None)
    if csrf:
        headers['X-CSRF-Token'] = csrf

    if data is not None:
        resp = session.post(url, json=data, headers=headers)
    else:
        resp = session.get(url, headers=headers)

    result = resp.json()

    if 'error' in result:
        print(f"  ERROR: {result['error']}")
        sys.exit(1)

    if isinstance(result, dict):
        user = result.get('user') or result
        if isinstance(user, dict) and 'csrf_token' in user:
            session._csrf = user['csrf_token']

    return result


def main():
    print("1. Registering client...")
    client = make_session()
    result = api(client, "auth.php?action=register", {
        "email": f"testclient{RUN_ID}@demo.local",
        "password": "test1234",
        "role": "client",
        "name": f"Test Client {RUN_ID}",
        "country": "Egypt",
        "timezone": "Africa/Cairo",
    })
    client_id = result['user']['id']
    print(f"   Client registered: ID={client_id}, Name={result['user']['name']}")

    print("2. Registering freelancer...")
    freelancer = make_session()
    result = api(freelancer, "auth.php?action=register", {
        "email": f"testfreelancer{RUN_ID}@demo.local",
        "password": "test1234",
        "role": "freelancer",
        "name": f"Test Freelancer {RUN_ID}",
        "country": "Germany",
        "timezone": "Europe/Berlin",
        "niche": "data_science",
        "hourly_rate": 100,
        "bio": "Python and ML expert.",
    })
    freelancer_id = result['user']['id']
    print(f"   Freelancer registered: ID={freelancer_id}, Name={result['user']['name']}")

    print("3. Client posting a job...")
    deadline = (datetime.now() + timedelta(days=30)).strftime("%Y-%m-%d %H:%M:%S")
    result = api(client, "client.php?action=jobs/create", {
        "title": "Build ML Pipeline",
        "description": "Need a production-ready ML pipeline for churn prediction.",
        "niche": "data_science",
        "budget": 5000,
        "deadline": deadline,
        "visibility": "public",
        "currency": "USD",
    })
    job_id = result['id']
    print(f"   Job posted: ID={job_id}")

    print("4. Freelancer browsing jobs...")
    jobs = api(freelancer, "client.php?action=jobs/browse")
    matching = [j for j in jobs if j['id'] == job_id]
    print(f"   Found {len(jobs)} jobs, target job {'visible' if matching else 'NOT FOUND'}")

    print("5. Freelancer submitting bid...")
    result = api(freelancer, "project.php?action=bids/submit", {
        "job_id": job_id,
        "amount": 4500,
        "proposal_text": "I have 5+ years of experience building ML pipelines. I can deliver this in 3 weeks.",
        "validity_days": 7,
    })
    bid_id = result['id']
    print(f"   Bid submitted: ID={bid_id}, Amount=$4500")

    print("6. Client viewing bids...")
    bids = api(client, f"client.php?action=bids&job_id={job_id}")
    print(f"   Received {len(bids)} bid(s)")
    for b in bids:
        print(f"   - Bid #{b['id']} by {b['freelancer_name']}: ${b['amount']}")

    print("7. Client accepting bid...")
    result = api(client, "client.php?action=bids/accept", {
        "bid_id": bid_id,
    })
    contract_id = result['contract_id']
    print(f"   Bid accepted! Contract created: ID={contract_id}")

    print("8. Verifying contract...")
    contract = api(client, f"project.php?action=contracts/{contract_id}")
    print(f"   Contract #{contract['id']}: {contract['scope_text']}")
    print(f"   Status: {contract['status']}")
    print(f"   Client: #{contract['client_id']} → Freelancer: #{contract['freelancer_id']}")
    print(f"   Total: ${contract['total_amount']}")

    print("\n All steps completed successfully!")


if __name__ == "__main__":
    main()
