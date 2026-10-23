"""Black-box integration tests against the real PHP/MariaDB Docker stack."""
import datetime, json, os, shutil, subprocess, unittest, urllib.parse, urllib.request, urllib.error, http.cookiejar, uuid
# Default is the separate test stack from tests/run.sh, never the local app on 8087.
TEST_URL = os.environ.get('TEST_URL', 'http://127.0.0.1:8088')
BASE = TEST_URL + '/api/index.php?action='
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
        self.assertEqual(anonymous.call('login',{'username':'missing_'+uuid.uuid4().hex[:8],'password':'wrong'})[0],401)
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
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit['visit_id'],'expected_total':1940})[0],409)
        for state in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':int(drink['id']),'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':int(drink['id']),'status':'collected'})[0],200)
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit['visit_id'],'expected_total':1940})[0],200)
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit['visit_id'],'expected_total':1940})[0],409)
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
        self.assertEqual(self.service.call('table_paid',{'visit_id':new_visit['visit_id'],'expected_total':350})[0],200)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
    def test_10_bill_number_and_cancellation(self):
        _,existing_tables=self.admin.call('tables')
        used={int(t['id']) for t in existing_tables}
        table_id=next(i for i in range(900,1000) if i not in used)
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Rechnung '+str(table_id)})[0],200)
        payload={'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':1,'quantity':2,'note':''},{'menu_id':6,'quantity':1,'note':''}]}
        code,created=self.service.call('order',payload)
        self.assertEqual(code,201,created)
        order_id=created['id']
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        self.assertEqual(bill['orders'][0]['id'],order_id)
        self.assertEqual(bill['orders'][0]['total'],3530)
        self.assertEqual(bill['total'],3530)
        self.assertEqual(len(bill['orders'][0]['items']),2)
        self.assertFalse(bill['can_pay'])
        _,active=self.service.call('tickets')
        food=next(t for t in active['tickets'] if int(t['order_id'])==order_id and t['station']=='kitchen')
        self.assertEqual(self.kitchen.call('status',{'id':int(food['id']),'status':'working'})[0],200)
        self.assertEqual(self.kitchen.call('table_bill&table_id='+str(table_id))[0],403)
        self.assertEqual(self.kitchen.call('order_cancel',{'order_id':order_id,'reason':'Test'})[0],403)
        self.assertEqual(self.service.call('order_cancel',{'order_id':order_id,'reason':''})[0],400)
        self.assertEqual(self.service.call('order_cancel',{'order_id':order_id,'reason':'Falsche Bestellung'})[0],200)
        self.assertEqual(self.service.call('order_cancel',{'order_id':order_id,'reason':'Erneut'})[0],409)
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        self.assertEqual(bill['total'],0)
        self.assertEqual(bill['orders'][0]['cancellation_reason'],'Falsche Bestellung')
        self.assertFalse(bill['can_pay'])
        _,active=self.service.call('tickets')
        self.assertFalse(any(int(t['order_id'])==order_id for t in active['tickets']))
        _,arch=self.service.call('tickets&archive=1')
        cancelled=[t for t in arch['tickets'] if int(t['order_id'])==order_id]
        self.assertEqual(len(cancelled),2)
        self.assertTrue(all(t['status']=='cancelled' and t['events'][-1]['action']=='cancelled' for t in cancelled))
        self.assertEqual(self.kitchen.call('status',{'id':int(food['id']),'status':'ready'})[0],409)
        _,overview=self.service.call('table_status')
        self.assertFalse(any(int(s['table_id'])==table_id for s in overview))
        second={'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':6,'quantity':1,'note':''}]}
        code,created=self.service.call('order',second)
        self.assertEqual(code,201,created)
        self.assertNotEqual(created['id'],order_id)
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        self.assertEqual(bill['total'],350)
        _,active=self.service.call('tickets')
        drink=next(t for t in active['tickets'] if int(t['order_id'])==created['id'])
        for state in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':int(drink['id']),'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':int(drink['id']),'status':'collected'})[0],200)
        self.assertEqual(self.service.call('table_paid',{'visit_id':bill['visit_id'],'expected_total':3530})[0],409)
        self.assertEqual(self.service.call('table_paid',{'visit_id':bill['visit_id'],'expected_total':350})[0],200)
        self.assertEqual(self.service.call('order_cancel',{'order_id':created['id'],'reason':'Zu spät'})[0],409)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
    def test_11_order_corrections(self):
        _,existing_tables=self.admin.call('tables')
        used={int(t['id']) for t in existing_tables}
        table_id=next(i for i in range(900,1000) if i not in used)
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Korrektur '+str(table_id)})[0],200)
        payload={'request_key':str(uuid.uuid4()),'table_id':table_id,'note':'Zusammen','items':[{'menu_id':1,'quantity':1,'note':'Ohne Zwiebel'},{'menu_id':6,'quantity':1,'note':''}]}
        code,created=self.service.call('order',payload)
        self.assertEqual(code,201,created)
        oid=created['id']
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        self.assertEqual(bill['total'],1940)
        food=next(i for i in bill['orders'][0]['items'] if i['station']=='kitchen')
        drink=next(i for i in bill['orders'][0]['items'] if i['station']=='bar')
        invalid={'order_id':oid,'mode':'remove','item_id':food['id'],'request_key':str(uuid.uuid4()),'reason':''}
        self.assertEqual(self.service.call('order_change',invalid)[0],400)
        self.assertEqual(self.kitchen.call('order_change',{**invalid,'reason':'Falsch'})[0],403)
        note={'order_id':oid,'mode':'note','note':'Getrennt servieren','reason':'Gastwunsch','request_key':str(uuid.uuid4())}
        self.assertEqual(self.service.call('order_change',note)[0],200)
        _,tickets=self.service.call('tickets')
        original_food=next(t for t in tickets['tickets'] if int(t['order_id'])==oid and t['station']=='kitchen')
        self.assertEqual(self.kitchen.call('status',{'id':int(original_food['id']),'status':'working'})[0],200)
        replace={'order_id':oid,'mode':'replace','item_id':food['id'],'menu_id':2,'quantity':2,'note':'Extra Zitrone','reason':'Gericht geändert','request_key':str(uuid.uuid4())}
        code,changed=self.service.call('order_change',replace)
        self.assertEqual(code,200,changed)
        self.assertTrue(changed['new_item_id'])
        code,repeated=self.service.call('order_change',replace)
        self.assertEqual(code,200,repeated)
        self.assertTrue(repeated['duplicate'])
        self.assertEqual(self.service.call('order_change',{**replace,'request_key':str(uuid.uuid4())})[0],409)
        add={'order_id':oid,'mode':'add','menu_id':7,'quantity':1,'note':'Kalt','reason':'Nachbestellung','request_key':str(uuid.uuid4())}
        self.assertEqual(self.service.call('order_change',add)[0],200)
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        order=bill['orders'][0]
        self.assertEqual(order['id'],oid)
        self.assertEqual(bill['total'],4110)
        self.assertEqual(order['note'],'Getrennt servieren')
        self.assertEqual(len(order['changes']),3)
        self.assertEqual([c['action'] for c in order['changes']],['note','replace','add'])
        self.assertEqual(next(i for i in order['items'] if i['id']==food['id'])['cancellation_reason'],'Gericht geändert')
        self.assertEqual(self.service.call('order_change',{'order_id':oid,'mode':'remove','item_id':drink['id'],'reason':'Nicht gewünscht','request_key':str(uuid.uuid4())})[0],200)
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        self.assertEqual(bill['total'],3760)
        _,active=self.service.call('tickets')
        new_tickets=[t for t in active['tickets'] if int(t['order_id'])==oid]
        self.assertEqual({t['origin'] for t in new_tickets},{'correction','addition'})
        self.assertEqual({t['status'] for t in new_tickets},{'new'})
        for ticket in new_tickets:
            station=self.kitchen if ticket['station']=='kitchen' else self.bar
            for state in ['working','ready']: self.assertEqual(station.call('status',{'id':int(ticket['id']),'status':state})[0],200)
            self.assertEqual(self.service.call('status',{'id':int(ticket['id']),'status':'collected'})[0],200)
        self.assertTrue(self.service.call('table_bill&table_id='+str(table_id))[1]['can_pay'])
        self.assertEqual(self.service.call('table_paid',{'visit_id':bill['visit_id'],'expected_total':4110})[0],409)
        self.assertEqual(self.service.call('table_paid',{'visit_id':bill['visit_id'],'expected_total':3760})[0],200)
        self.assertTrue(self.service.call('order_change',add)[1]['duplicate'])
        self.assertEqual(self.service.call('order_change',{'order_id':oid,'mode':'add','menu_id':6,'quantity':1,'reason':'Zu spät','request_key':str(uuid.uuid4())})[0],409)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
    def test_12_staff_edit_roles_and_removal(self):
        suffix=uuid.uuid4().hex[:8]
        initial='team_'+suffix
        renamed='dienst_'+suffix
        password='test-password-123'
        code,created=self.admin.call('admin_user',{'name':'Testkraft','username':initial,'password':password,'roles':['service','kitchen','bar']})
        self.assertEqual(code,201,created)
        _,people=self.admin.call('admin_users')
        person=next(p for p in people if p['username']==initial)
        staff_id=int(person['id'])
        self.assertEqual(set(person['roles']),{'service','kitchen','bar'})
        changed={'id':staff_id,'name':'Korrigierte Testkraft','username':renamed,'roles':['service','kitchen','bar']}
        self.assertEqual(self.service.call('admin_user_update',changed)[0],403)
        self.assertEqual(self.service.call('admin_user_status',{'id':staff_id,'active':False})[0],403)
        self.assertEqual(self.admin.call('admin_user_update',{**changed,'roles':['invalid']})[0],400)
        self.assertEqual(self.admin.call('admin_user_update',{**changed,'username':'mia'})[0],409)
        self.assertEqual(self.admin.call('admin_user_update',changed)[0],200)
        self.assertEqual(Client().call('login',{'username':initial,'password':password})[0],401)
        worker=Client()
        code,login=worker.call('login',{'username':renamed,'password':password})
        self.assertEqual(code,200,login)
        worker.csrf=login['csrf']
        self.assertEqual(set(login['user']['roles']),{'service','kitchen','bar'})
        _,existing_tables=self.admin.call('tables')
        used={int(t['id']) for t in existing_tables}
        table_id=next(i for i in range(900,1000) if i not in used)
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Personal '+str(table_id)})[0],200)
        code,order=worker.call('order',{'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':1,'quantity':1,'note':''},{'menu_id':6,'quantity':1,'note':''}]})
        self.assertEqual(code,201,order)
        _,board=worker.call('tickets')
        tickets=[t for t in board['tickets'] if int(t['order_id'])==order['id']]
        self.assertEqual(len(tickets),2)
        kitchen=next(t for t in tickets if t['station']=='kitchen')
        bar=next(t for t in tickets if t['station']=='bar')
        self.assertEqual(worker.call('status',{'id':int(kitchen['id']),'status':'working'})[0],200)
        self.assertEqual(worker.call('status',{'id':int(bar['id']),'status':'working'})[0],200)
        self.assertEqual(self.admin.call('admin_user_update',{**changed,'roles':['service','bar']})[0],200)
        self.assertEqual(worker.call('status',{'id':int(kitchen['id']),'status':'ready'})[0],403)
        self.assertEqual(worker.call('status',{'id':int(bar['id']),'status':'ready'})[0],200)
        self.assertEqual(self.admin.call('admin_user_status',{'id':staff_id,'active':False})[0],200)
        self.assertEqual(worker.call('session')[0],401)
        self.assertEqual(Client().call('login',{'username':renamed,'password':password})[0],401)
        _,people=self.admin.call('admin_users')
        self.assertEqual(int(next(p for p in people if int(p['id'])==staff_id)['active']),0)
        _,admin_board=self.admin.call('tickets')
        self.assertEqual(next(t for t in admin_board['tickets'] if int(t['order_id'])==order['id'])['server'],'Korrigierte Testkraft')
        self.assertEqual(self.admin.call('admin_user_status',{'id':staff_id,'active':True})[0],200)
        self.assertEqual(Client().call('login',{'username':renamed,'password':password})[0],200)
        _,admins=self.admin.call('admin_users')
        admin_id=int(next(p for p in admins if p['username']=='admin')['id'])
        self.assertEqual(self.admin.call('admin_user_status',{'id':admin_id,'active':False})[0],409)
        self.assertEqual(self.admin.call('admin_user_update',{'id':admin_id,'name':'Admin','username':'admin','roles':['service']})[0],409)
        self.assertEqual(self.admin.call('order_cancel',{'order_id':order['id'],'reason':'Test abgeschlossen'})[0],200)
        self.assertEqual(self.admin.call('admin_user_status',{'id':staff_id,'active':False})[0],200)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
    def test_13_late_cancellation_requires_station_ack(self):
        _,existing=self.admin.call('tables')
        used={int(t['id']) for t in existing}
        table_id=next(i for i in range(900,1000) if i not in used)
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Storno '+str(table_id)})[0],200)
        code,order=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':1,'quantity':1,'note':''},{'menu_id':2,'quantity':1,'note':''}]})
        self.assertEqual(code,201,order)
        oid=order['id']
        _,board=self.kitchen.call('tickets')
        ticket=next(t for t in board['tickets'] if int(t['order_id'])==oid)
        ticket_id=int(ticket['id'])
        self.assertEqual(self.kitchen.call('status',{'id':ticket_id,'status':'working'})[0],200)
        item_id=int(ticket['items'][0]['id'])
        self.assertEqual(self.service.call('order_change',{'order_id':oid,'mode':'remove','item_id':item_id,'reason':'Gastwunsch','request_key':str(uuid.uuid4())})[0],200)
        _,board=self.kitchen.call('tickets')
        alert=next(a for a in board['alerts'] if int(a['ticket_id'])==ticket_id)
        self.assertEqual(alert['kind'],'item_cancelled')
        self.assertEqual(self.bar.call('station_alert_ack',{'id':int(alert['id'])})[0],403)
        self.assertEqual(self.kitchen.call('status',{'id':ticket_id,'status':'ready'})[0],409)
        self.assertEqual(self.kitchen.call('station_alert_ack',{'id':int(alert['id'])})[0],200)
        self.assertEqual(self.kitchen.call('status',{'id':ticket_id,'status':'ready'})[0],200)
        self.assertEqual(self.service.call('order_cancel',{'order_id':oid,'reason':'Tisch storniert'})[0],200)
        _,board=self.kitchen.call('tickets')
        late=next(a for a in board['alerts'] if int(a['ticket_id'])==ticket_id)
        self.assertEqual(late['kind'],'order_cancelled')
        self.assertEqual(self.kitchen.call('station_alert_ack',{'id':int(late['id'])})[0],200)
        self.assertFalse(any(int(a['ticket_id'])==ticket_id for a in self.kitchen.call('tickets')[1]['alerts']))
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
    def test_14_payment_confirmation_can_be_reversed_once(self):
        _,existing=self.admin.call('tables')
        used={int(t['id']) for t in existing}
        table_id=next(i for i in range(900,1000) if i not in used)
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Zahlung '+str(table_id)})[0],200)
        code,order=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':6,'quantity':1,'note':''}]})
        self.assertEqual(code,201,order)
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        visit_id=bill['visit_id']
        _,board=self.bar.call('tickets')
        ticket_id=int(next(t for t in board['tickets'] if int(t['order_id'])==order['id'])['id'])
        for state in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':ticket_id,'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':ticket_id,'status':'collected'})[0],200)
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit_id,'expected_total':350})[0],200)
        self.assertEqual(self.kitchen.call('payments')[0],403)
        self.assertEqual(self.service.call('payment_reverse',{'visit_id':visit_id,'reason':''})[0],400)
        self.assertEqual(self.service.call('payment_reverse',{'visit_id':visit_id,'reason':'Falsch bestätigt'})[0],200)
        self.assertEqual(self.service.call('payment_reverse',{'visit_id':visit_id,'reason':'Erneut'})[0],409)
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        self.assertEqual(bill['visit_id'],visit_id)
        self.assertEqual(bill['total'],350)
        _,payments=self.service.call('payments')
        history=[e for e in payments['events'] if int(e['visit_id'])==visit_id]
        self.assertEqual([e['action'] for e in history[:2]],['reversed','confirmed'])
        self.assertEqual(history[0]['reason'],'Falsch bestätigt')
        self.assertEqual(self.service.call('table_paid',{'visit_id':visit_id,'expected_total':350})[0],200)
        code,next_order=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':6,'quantity':1,'note':''}]})
        self.assertEqual(code,201,next_order)
        self.assertEqual(self.service.call('payment_reverse',{'visit_id':visit_id,'reason':'Alter Besuch'})[0],409)
        self.assertFalse(any(p['visit_id']==visit_id for p in self.service.call('payments')[1]['paid']))
        self.assertEqual(self.service.call('order_cancel',{'order_id':next_order['id'],'reason':'Test abgeschlossen'})[0],200)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
    def test_15_password_whitespace_is_kept(self):
        username='space_'+uuid.uuid4().hex[:8];password='  Leerzeichen-Pass-2026  '
        self.assertEqual(self.admin.call('admin_user',{'name':'Leerzeichen','username':username,'password':password,'roles':['kitchen']})[0],201)
        c=Client();code,data=c.call('login',{'username':username,'password':password.strip()})
        self.assertEqual(code,401,data)
        code,data=c.call('login',{'username':username,'password':password});self.assertEqual(code,200,data)
        self.assertEqual(data['user']['username'],username)
    def test_16_timestamps_use_utc(self):
        _,oid=self.order()
        _,data=self.service.call('tickets')
        ticket=next(t for t in data['tickets'] if int(t['order_id'])==oid)
        parse=lambda s:datetime.datetime.fromisoformat(s.replace(' ','T'))
        self.assertLess(abs((parse(data['server_time'])-parse(ticket['created_at'])).total_seconds()),60)
        out=in_app("$tz=query('SELECT @@session.time_zone AS tz, TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW()) AS diff')->fetch();echo json_encode([$tz['tz'],(int)$tz['diff'],date_default_timezone_get()]);")
        self.assertEqual(json.loads(out),['+00:00',0,'UTC'])
    def test_17_unreachable_database_returns_json_503(self):
        # A session with a user forces a DB query, which must fail cleanly without a second connection attempt.
        out=in_app("session_id('dbfail'.bin2hex(random_bytes(4)));session_start();$_SESSION['uid']=1;session_write_close();$_GET['action']='tickets';$_SERVER['REQUEST_METHOD']='GET';register_shutdown_function(function(){fwrite(STDERR,(string)http_response_code());});include '/var/www/html/api/index.php';",
            env={'DB_HOST':'127.0.0.1','DB_PASSWORD':'wrong'},require_db=False)
        self.assertEqual(json.loads(out),{'error':'Datenbank vorübergehend nicht erreichbar.'})
def in_app(code,env=None,require_db=True):
    """Run PHP inside the local app container; skipped when the Docker stack is not available."""
    if urllib.parse.urlparse(TEST_URL).hostname not in ('127.0.0.1','localhost') or not shutil.which('docker'): raise unittest.SkipTest('Nur gegen den lokalen Docker-Teststack.')
    prefix="require '/var/www/html/api/db.php';" if require_db else ''
    args=['docker','compose','-p',os.environ.get('COMPOSE_PROJECT_NAME','kittchenapp-test'),'exec','-T']+[f'-e{k}={v}' for k,v in (env or {}).items()]+['app','php','-r',prefix+code]
    result=subprocess.run(args,cwd=os.path.dirname(os.path.dirname(os.path.abspath(__file__))),capture_output=True,text=True,timeout=30)
    return result.stdout
if __name__=='__main__': unittest.main(verbosity=2)
