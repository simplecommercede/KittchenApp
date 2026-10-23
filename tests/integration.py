"""Black-box integration tests against the real PHP/MariaDB Docker stack."""
import json, os, unittest, urllib.request, urllib.error, http.cookiejar, uuid
BASE = os.environ.get('TEST_URL', 'http://127.0.0.1:8087') + '/api/index.php?action='
class Client:
    def __init__(self, name=None):
        self.http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ''
        _, data = self.call('session')
        self.csrf = data['csrf']
        if name:
            status, data = self.call('login', {'username':name, 'password':'Stimbeck-Demo-2026!'})
            assert status == 200, data
            self.csrf = data['csrf']
    def call(self, action, data=None, csrf=True):
        headers = {'Content-Type':'application/json'}
        if csrf: headers['X-CSRF-Token'] = self.csrf
        req = urllib.request.Request(BASE+action, data=json.dumps(data).encode() if data is not None else None, headers=headers)
        try:
            with self.http.open(req, timeout=10) as r: return r.status, json.load(r)
        except urllib.error.HTTPError as e:
            try: return e.code, json.load(e)
            finally: e.close()
class Workflow(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.service=Client('mia'); cls.kitchen=Client('robin'); cls.bar=Client('tresen'); cls.admin=Client('admin')
    def order(self):
        payload={'request_key':str(uuid.uuid4()), 'table_id':7, 'note':'Zusammen servieren', 'items':[{'menu_id':1,'quantity':1,'note':'Ohne Tomate · Extra Gurke'},{'menu_id':6,'quantity':2,'note':''}]}
        code,data=self.service.call('order',payload);self.assertEqual(code,201,data)
        return payload,data['id']
    def test_01_auth_and_csrf(self):
        anonymous=Client()
        self.assertEqual(anonymous.call('tickets')[0],401)
        self.assertEqual(self.service.call('order',{},csrf=False)[0],403)
        self.assertEqual(anonymous.call('login',{'username':'missing','password':'wrong'})[0],401)
    def test_02_permissions(self):
        self.assertEqual(self.kitchen.call('order',{})[0],403)
        self.assertEqual(self.service.call('admin_users')[0],403)
        self.assertEqual(self.bar.call('admin_menu',{})[0],403)
    def test_03_full_workflow(self):
        payload,oid=self.order()
        code,repeated=self.service.call('order',payload)
        self.assertEqual(code,200); self.assertEqual(repeated['id'],oid);self.assertTrue(repeated['duplicate'])
        _,data=self.service.call('tickets')
        tickets=[t for t in data['tickets'] if int(t['order_id'])==oid]
        self.assertEqual(len(tickets),2)
        food=next(t for t in tickets if t['station']=='kitchen'); drink=next(t for t in tickets if t['station']=='bar')
        self.assertEqual(food['items'][0]['note'],'Ohne Tomate · Extra Gurke')
        self.assertEqual(int(drink['items'][0]['quantity']),2)
        _,kdata=self.kitchen.call('tickets');self.assertTrue(all(t['station']=='kitchen' for t in kdata['tickets']))
        _,bdata=self.bar.call('tickets');self.assertTrue(all(t['station']=='bar' for t in bdata['tickets']))
        fid=int(food['id'])
        self.assertEqual(self.bar.call('status',{'id':fid,'status':'working'})[0],403)
        self.assertEqual(self.kitchen.call('status',{'id':fid,'status':'ready'})[0],409)
        self.assertEqual(self.kitchen.call('status',{'id':fid,'status':'working'})[0],200)
        self.assertEqual(self.kitchen.call('status',{'id':fid,'status':'working'})[0],409)
        self.assertEqual(self.kitchen.call('status',{'id':fid,'status':'ready'})[0],200)
        self.assertEqual(self.kitchen.call('status',{'id':fid,'status':'collected'})[0],403)
        self.assertEqual(self.service.call('status',{'id':fid,'status':'collected'})[0],200)
        _,arch=self.service.call('tickets&archive=1'); saved=next(t for t in arch['tickets'] if int(t['id'])==fid)
        self.assertEqual([e['action'] for e in saved['events']],['new','working','ready','collected'])
        _,active=self.service.call('tickets'); self.assertFalse(any(int(t['id'])==fid for t in active['tickets']))
        did=int(drink['id'])
        for status in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':did,'status':status})[0],200)
        self.assertEqual(self.service.call('status',{'id':did,'status':'collected'})[0],200)
    def test_04_invalid_orders(self):
        for items in [[],[{'menu_id':1,'quantity':0}],[{'menu_id':999999,'quantity':1}]]:
            self.assertEqual(self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':7,'items':items})[0],400)
    def test_05_admin(self):
        suffix=uuid.uuid4().hex[:8]
        self.assertEqual(self.admin.call('admin_user',{'name':'Test user','username':'test_'+suffix,'password':'test-password-123','roles':['kitchen','bar']})[0],201)
        self.assertEqual(self.admin.call('admin_user',{'name':'Invalid','username':'invalid','password':'test-password-123','roles':['superuser']})[0],400)
        self.assertEqual(self.admin.call('admin_table',{'id':99,'name':'Testtisch'})[0],200)
        self.assertEqual(self.admin.call('admin_menu',{'name':'Testgericht '+suffix,'category':'Tests','station':'kitchen','price':100})[0],200)
    def test_06_snapshot_and_atomic_validation(self):
        payload,oid=self.order()
        _,before=self.service.call('tickets')
        ticket=next(t for t in before['tickets'] if int(t['order_id'])==oid and t['station']=='kitchen')
        original=ticket['items'][0]['name']
        self.assertEqual(self.admin.call('admin_menu',{'id':1,'name':'Temporär geändert','category':'Speisen','station':'kitchen','price':999})[0],200)
        try:
            _,after=self.service.call('tickets')
            saved=next(t for t in after['tickets'] if int(t['id'])==int(ticket['id']))
            self.assertEqual(saved['items'][0]['name'],original)
            self.assertEqual(int(saved['items'][0]['price']),1590)
        finally:
            self.admin.call('admin_menu',{'id':1,'name':'Hamburger mit Bacon','category':'Speisen','station':'kitchen','price':1590})
        payload['request_key']=str(uuid.uuid4())
        payload['items'].append({'menu_id':999999,'quantity':1})
        self.assertEqual(self.service.call('order',payload)[0],400)
        _,final=self.service.call('tickets')
        self.assertEqual(len(final['tickets']),len(before['tickets']))
    def test_06_logout(self):
        c=Client('mia');self.assertEqual(c.call('logout',{})[0],200);self.assertEqual(c.call('tickets')[0],401)
    def test_07_login_throttling(self):
        c=Client();name='invalid_'+uuid.uuid4().hex
        for _ in range(5): self.assertEqual(c.call('login',{'username':name,'password':'bad'})[0],401)
        self.assertEqual(c.call('login',{'username':name,'password':'bad'})[0],429)
    def test_08_temporary_availability(self):
        self.assertEqual(self.service.call('admin_table_status',{'id':7,'active':False})[0],403)
        original={'request_key':str(uuid.uuid4()),'table_id':7,'items':[{'menu_id':1,'quantity':1,'note':''}]}
        created,order=self.service.call('order',original)
        self.assertEqual(created,201,order)
        try:
            self.assertEqual(self.admin.call('admin_table_status',{'id':7,'active':False})[0],200)
            _,tables=self.service.call('tables')
            self.assertEqual(int(next(t for t in tables if int(t['id'])==7)['active']),0)
            repeated,existing=self.service.call('order',original)
            self.assertEqual(repeated,200,existing)
            self.assertEqual(existing['id'],order['id'])
            self.assertEqual(self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':7,'items':[{'menu_id':1,'quantity':1,'note':''}]})[0],400)
        finally:
            self.admin.call('admin_table_status',{'id':7,'active':True})
        try:
            self.assertEqual(self.admin.call('admin_menu_status',{'id':1,'active':False})[0],200)
            _,menu=self.service.call('menu')
            self.assertEqual(int(next(m for m in menu if int(m['id'])==1)['active']),0)
            self.assertEqual(self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':7,'items':[{'menu_id':1,'quantity':1,'note':''}]})[0],400)
        finally:
            self.admin.call('admin_menu_status',{'id':1,'active':True})
    def test_09_table_visit_and_payment(self):
        self.assertEqual(self.kitchen.call('table_status')[0],403)
        _,existing_tables=self.admin.call('tables')
        used={int(t['id']) for t in existing_tables}
        table_id=next(i for i in range(900,1000) if i not in used)
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Zahltest '+str(table_id)})[0],200)
        def send(menu_id):
            payload={'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':menu_id,'quantity':1,'note':''}]}
            code,data=self.service.call('order',payload)
            self.assertEqual(code,201,data)
            return data['id']
        food_order=send(1)
        _,overview=self.service.call('table_status')
        visit=next(s for s in overview if int(s['table_id'])==table_id)
        self.assertEqual(int(visit['food_pending']),1)
        self.assertFalse(visit['payment_due'])
        drink_order=send(6)
        _,overview=self.service.call('table_status')
        self.assertEqual(next(s for s in overview if int(s['table_id'])==table_id)['visit_id'],visit['visit_id'])
        _,data=self.service.call('tickets')
        food=next(t for t in data['tickets'] if int(t['order_id'])==food_order)
        drink=next(t for t in data['tickets'] if int(t['order_id'])==drink_order)
        for state in ['working','ready']: self.assertEqual(self.kitchen.call('status',{'id':int(food['id']),'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':int(food['id']),'status':'collected'})[0],200)
        _,overview=self.service.call('table_status')
        after_food=next(s for s in overview if int(s['table_id'])==table_id)
        self.assertTrue(after_food['payment_due'])
        self.assertEqual(int(after_food['pending_count']),1)
        self.assertEqual(self.kitchen.call('table_paid',{'visit_id':visit['visit_id']})[0],403)
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit['visit_id']})[0],409)
        for state in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':int(drink['id']),'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':int(drink['id']),'status':'collected'})[0],200)
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit['visit_id']})[0],200)
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit['visit_id']})[0],409)
        _,overview=self.service.call('table_status')
        self.assertFalse(any(int(s['table_id'])==table_id for s in overview))
        next_order=send(6)
        _,overview=self.service.call('table_status')
        new_visit=next(s for s in overview if int(s['table_id'])==table_id)
        self.assertNotEqual(new_visit['visit_id'],visit['visit_id'])
        _,data=self.service.call('tickets')
        next_drink=next(t for t in data['tickets'] if int(t['order_id'])==next_order)
        for state in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':int(next_drink['id']),'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':int(next_drink['id']),'status':'collected'})[0],200)
        self.assertEqual(self.service.call('table_paid',{'visit_id':new_visit['visit_id']})[0],200)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
if __name__=='__main__': unittest.main(verbosity=2)
