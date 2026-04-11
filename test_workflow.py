import requests
import json
import os
import sys
from datetime import datetime, timedelta

BASE = "http://localhost:8080/api"

def api(session, endpoint, method="GET", body=None, files=None, csrf=None):
    url = f"{BASE}/{endpoint}"
    headers = {}
    if csrf:
        headers["X-CSRF-Token"] = csrf
    if method == "POST" and body is not None and files is None:
        headers["Content-Type"] = "application/json"
        resp = session.post(url, data=json.dumps(body), headers=headers)
    elif method == "POST" and files is not None:
        resp = session.post(url, files=files, headers=headers)
    else:
        resp = session.get(url, headers=headers)
    return resp

def login(email, password):
    s = requests.Session()
    r = s.post(f"{BASE}/auth.php?action=login",
               json={"email": email, "password": password},
               headers={"Content-Type": "application/json"})
    assert r.status_code == 200, f"Login failed for {email}: {r.text}"
    user = r.json()
    # normalize — response may be {user: {...}} or flat
    if "user" in user:
        user = user["user"]
    csrf = user.get("csrf_token", "")
    print(f"  ✓ Logged in as {email} (role={user['role']}, csrf={csrf[:8]}...)")
    return s, csrf, user

def check(label, resp, expected_status=200):
    if resp.status_code == expected_status:
        try:
            val = resp.json()
            print(f"  ✓ {label}")
            return val
        except Exception:
            print(f"  ✓ {label} (Warning: returned non-JSON text: {resp.text[:200]})")
            return resp.text
    else:
        print(f"  ✗ {label} — HTTP {resp.status_code}: {resp.text[:500]}")
        return None

# ── run all phases ──────────────────────────────────────────────────────────
if __name__ == "__main__":
    results = {}

    # ── PHASE 1: CLIENT POSTS JOB ──────────────────────────────────────────
    print("\n=== PHASE 1: Client posts a job ===")
    sc, csrf_c, uc = login("robert.client@example.com", "admin123")
    deadline = (datetime.utcnow() + timedelta(days=90)).strftime("%Y-%m-%d %H:%M:%S")
    r = api(sc, "client.php?action=jobs/create", "POST", {
        "title": "QA Test — Data Pipeline Audit",
        "niche": "data_science",
        "description": "End-to-end automated QA test job",
        "budget": 3000,
        "currency": "USD",
        "deadline": deadline,
        "visibility": "public",
        "data_stack": "Python, Spark",
        "dataset_size": "10GB",
        "deliverable_format": "Jupyter Notebook"
    }, csrf=csrf_c)
    data = check("Create job", r, 200)
    job_id = data["id"] if data else None
    results["job_id"] = job_id
    print(f"  → job_id = {job_id}")

    # verify job appears in browse
    r = api(sc, f"client.php?action=jobs/browse&keyword=QA+Test")
    jobs = check("Browse jobs — new job visible", r, 200)
    found = any(j["id"] == job_id for j in (jobs or []))
    print(f"  {'✓' if found else '✗'} Job {job_id} visible in browse results")

    # ── PHASE 2: FREELANCER BROWSES AND BIDS ──────────────────────────────
    print("\n=== PHASE 2: Freelancer submits a bid ===")
    sf, csrf_f, uf = login("elena.data@example.com", "admin123")
    r = api(sf, f"client.php?action=jobs/browse&keyword=QA+Test") # Wait - is job browse accessible by freelancer in client.php?
    jobs = check("Freelancer browses jobs", r, 200)
    if jobs:
        found = any(j["id"] == job_id for j in (jobs or []))
    else:
        found = False
    print(f"  {'✓' if found else '✗'} Job visible to freelancer")

    bid_due = (datetime.utcnow() + timedelta(days=14)).strftime("%Y-%m-%d %H:%M:%S")
    r = api(sf, "project.php?action=bids/submit", "POST", {
        "job_id": job_id,
        "amount": 2800,
        "validity_days": 14,
        "proposal_text": "I can deliver a full Spark pipeline audit with documented notebooks."
    }, csrf=csrf_f)
    data = check("Submit bid", r, 200)
    bid_id = data["id"] if data else None
    results["bid_id"] = bid_id
    print(f"  → bid_id = {bid_id}")

    r = api(sf, "project.php?action=bids/mine")
    bids = check("Freelancer sees own bid", r, 200)
    found = any(b["id"] == bid_id for b in (bids or []))
    print(f"  {'✓' if found else '✗'} Bid {bid_id} in bids/mine")

    # ── PHASE 3: CLIENT ACCEPTS BID ────────────────────────────────────────
    print("\n=== PHASE 3: Client accepts bid → contract created ===")
    r = api(sc, "client.php?action=bids/accept", "POST", {
        "bid_id": bid_id,
        "partial_release_pct": 20
    }, csrf=csrf_c)
    data = check("Accept bid", r, 200)
    contract_id = data.get("contract_id") if data else None
    results["contract_id"] = contract_id
    print(f"  → contract_id = {contract_id}")

    r = api(sc, f"project.php?action=contracts/{contract_id}")
    contract = check("Fetch contract detail", r, 200)
    if contract:
        print(f"  → contract status = {contract.get('status')}")
        assert contract.get("status") == "pending_nda", f"Expected pending_nda, got {contract.get('status')}"

    # ── PHASE 4: NDA SIGNING ────────────────────────────────────────────────
    print("\n=== PHASE 4: Both parties sign NDA ===")
    # client signs
    r = api(sc, "client.php?action=contracts/nda/sign", "POST", {
        "job_id": job_id
    }, csrf=csrf_c)
    check("Client signs NDA", r, 200)

    # freelancer signs
    r = api(sf, "project.php?action=contracts/nda/sign", "POST", {
        "job_id": job_id
    }, csrf=csrf_f)
    check("Freelancer signs NDA", r, 200)

    # verify contract now active
    r = api(sc, f"project.php?action=contracts/{contract_id}")
    contract = check("Contract now active after both NDA signatures", r, 200)
    if contract:
        status = contract.get("status")
        print(f"  {'✓' if status == 'active' else '✗'} Contract status = {status} (expected: active)")

    # ── PHASE 5: BUILD MILESTONES ───────────────────────────────────────────
    print("\n=== PHASE 5: Build milestones ===")
    now = datetime.utcnow()
    r = api(sf, "project.php?action=contracts/milestones/build", "POST", {
        "contract_id": contract_id,
        "milestones": [
            {
                "title": "Data Ingestion",
                "amount": 1000,
                "due_date": (now + timedelta(days=30)).strftime("%Y-%m-%d %H:%M:%S"),
                "order_index": 1,
                "dependency_milestone_id": None
            },
            {
                "title": "Pipeline Build",
                "amount": 1200,
                "due_date": (now + timedelta(days=60)).strftime("%Y-%m-%d %H:%M:%S"),
                "order_index": 2,
                "dependency_milestone_id": None  # will patch after M1 ID known
            },
            {
                "title": "Final Report",
                "amount": 600,
                "due_date": (now + timedelta(days=90)).strftime("%Y-%m-%d %H:%M:%S"),
                "order_index": 3,
                "dependency_milestone_id": None
            }
        ]
    }, csrf=csrf_f)
    # NOTE: 1000+1200+600 = 2800 = contract total. Adjust if contract total differs.
    data = check("Build milestones", r, 200)
    milestone_ids = data["ids"] if data and "ids" in data else []
    if type(data) is list and len(milestone_ids) == 0:
      milestone_ids = data
    results["milestone_ids"] = milestone_ids
    print(f"  → milestone_ids = {milestone_ids}")
    m1 = milestone_ids[0] if len(milestone_ids) > 0 else None
    m2 = milestone_ids[1] if len(milestone_ids) > 1 else None
    m3 = milestone_ids[2] if len(milestone_ids) > 2 else None

    # ── PHASE 6: ESCROW LOCK + MILESTONE START ─────────────────────────────
    print("\n=== PHASE 6: Lock escrow for M1 and start milestone ===")
    # financial_admin locks escrow
    sa, csrf_a, ua = login("finance@specialisthub.local", "admin123")
    r = api(sa, "escrow.php?action=lock", "POST", {
        "milestone_id": m1
    }, csrf=csrf_a)
    check(f"Lock escrow for milestone {m1}", r, 200)

    # freelancer starts milestone
    r = api(sf, "project.php?action=milestones/start", "POST", {
        "milestone_id": m1
    }, csrf=csrf_f)
    check("Start milestone M1", r, 200)

    r = api(sf, f"project.php?action=milestones/{m1}")
    m = check("Milestone M1 status check", r, 200)
    if m:
        print(f"  {'✓' if m.get('status') == 'in_progress' else '✗'} M1 status = {m.get('status')} (expected: in_progress)")

    # ── PHASE 7: QA CHECKLIST + DELIVERABLE ────────────────────────────────
    print("\n=== PHASE 7: QA checklist + deliverable submission ===")
    r = api(sf, "project.php?action=contracts/qa-checklist/submit", "POST", {
        "milestone_id": m1,
        "checklist": {
            "files_complete": True,
            "requirements_met": True,
            "no_placeholder_data": True,
            "formats_match": True
        }
    }, csrf=csrf_f)
    check("Submit QA checklist for M1", r, 200)

    # create a tiny dummy file and upload
    dummy_file_path = "/tmp/qa_test_deliverable.txt"
    with open(dummy_file_path, "w") as f:
        f.write("QA test deliverable content — automated test run\n")

    with open(dummy_file_path, "rb") as f:
        r = api(sf,
                "project.php?action=milestones/submit",
                "POST",
                files={
                    "file": ("qa_deliverable.txt", f, "text/plain"),
                    "milestone_id": (None, str(m1))
                },
                csrf=csrf_f)
    data = check("Submit deliverable for M1", r, 200)
    deliverable_id = data.get("id") if data else None
    print(f"  → deliverable_id = {deliverable_id}")

    r = api(sf, f"project.php?action=milestones/{m1}")
    m = check("M1 status after deliverable", r, 200)
    if m:
        print(f"  {'✓' if m.get('status') == 'submitted' else '✗'} M1 status = {m.get('status')} (expected: submitted)")

    # ── PHASE 8: CLIENT APPROVES MILESTONE ─────────────────────────────────
    print("\n=== PHASE 8: Client approves M1 → escrow release ===")
    r = api(sc, "project.php?action=milestones/approve", "POST", {
        "milestone_id": m1
    }, csrf=csrf_c)
    check("Client approves M1", r, 200)

    r = api(sc, f"project.php?action=milestones/{m1}")
    m = check("M1 status after approval", r, 200)
    if m:
        print(f"  {'✓' if m.get('status') in ('approved','auto_approved') else '✗'} M1 status = {m.get('status')}")

    # verify escrow release transaction created
    r = api(sc, f"escrow.php?action=ledger&contract_id={contract_id}")
    ledger = check("Escrow ledger has release transaction", r, 200)
    if ledger:
        all_txs = []
        for currency_group in ledger.values():
            all_txs.extend(currency_group.get("transactions", []))
        releases = [t for t in all_txs if t["type"] in ("release", "partial_release")]
        print(f"  {'✓' if releases else '✗'} Found {len(releases)} release transaction(s)")

    # ── PHASE 9: FREELANCER CONFIRMS COMPLETION ────────────────────────────
    print("\n=== PHASE 9: Freelancer confirms M1 complete ===")
    r = api(sf, "project.php?action=milestones/confirm", "POST", {
        "milestone_id": m1
    }, csrf=csrf_f)
    check("Freelancer confirms M1 complete", r, 200)

    r = api(sf, f"project.php?action=milestones/{m1}")
    m = check("M1 final status check", r, 200)
    if m:
        print(f"  {'✓' if m.get('status') == 'complete' else '✗'} M1 status = {m.get('status')} (expected: complete)")

    # ── PHASE 10: ESCROW DASHBOARD ─────────────────────────────────────────
    print("\n=== PHASE 10: Escrow dashboard verification ===")
    r = api(sc, f"escrow.php?action=balance&contract_id={contract_id}")
    bal = check("Escrow balance", r, 200)
    if bal:
        print(f"  → pending={bal.get('pending_balance')}  cleared={bal.get('cleared_balance')}")

    r = api(sc, f"escrow.php?action=fees&contract_id={contract_id}")
    fees = check("Platform fees", r, 200)
    if fees:
        print(f"  → fee={fees.get('fee_percentage')}%  lifetime={fees.get('lifetime_value')}")

    r = api(sc, f"escrow.php?action=tax&contract_id={contract_id}")
    tax = check("Tax calculation", r, 200)
    if tax:
        print(f"  → freelancer_net={tax.get('freelancer_net')}  tax_on_fee={tax.get('tax_on_fee')}")

    # ── PHASE 11: CONTRACT MESSAGING ───────────────────────────────────────
    print("\n=== PHASE 11: Contract messaging ===")
    r = api(sc, "project.php?action=contracts/message", "POST", {
        "contract_id": contract_id,
        "message": "Milestone 1 looks great. Please proceed with the pipeline build."
    }, csrf=csrf_c)
    check("Client sends contract message", r, 200)

    r = api(sf, f"project.php?action=contracts/{contract_id}/messages")
    msgs = check("Freelancer reads contract messages", r, 200)
    if msgs:
        found = any("pipeline build" in (m.get("message") or "") for m in msgs)
        print(f"  {'✓' if found else '✗'} Message visible to freelancer")

    # ── PHASE 12: DISPUTE SMOKE TEST ───────────────────────────────────────
    print("\n=== PHASE 12: Dispute smoke test ===")
    r = api(sc, "dispute.php?action=file", "POST", {
        "contract_id": contract_id,
        "reason": "Automated QA dispute smoke test"
    }, csrf=csrf_c)
    data = check("File dispute", r, 200)
    dispute_id = data.get("id") if data else None
    print(f"  → dispute_id = {dispute_id}")

    # admin views disputes
    sad, csrf_ad, uad = login("admin@specialisthub.local", "admin123")
    r = api(sad, "dispute.php?action=mine")
    disputes = check("Admin fetches dispute list", r, 200)
    if disputes:
        found = any(d["id"] == dispute_id for d in disputes)
        print(f"  {'✓' if found else '✗'} Dispute {dispute_id} visible to admin")

    # safe-room message
    r = api(sad, "dispute.php?action=message", "POST", {
        "dispute_id": dispute_id,
        "message": "Admin acknowledges this QA test dispute."
    }, csrf=csrf_ad)
    check("Admin sends safe-room message", r, 200)

    # ── SUMMARY ─────────────────────────────────────────────────────────────
    print("\n=== SUMMARY ===")
    print(f"  job_id       = {results.get('job_id')}")
    print(f"  bid_id       = {results.get('bid_id')}")
    print(f"  contract_id  = {results.get('contract_id')}")
    print(f"  milestone_ids = {results.get('milestone_ids')}")
    print("\nAll phases complete. Review ✗ lines above for any failures.")
