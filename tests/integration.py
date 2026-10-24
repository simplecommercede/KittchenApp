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
        out=in_app("$driver=Kitchen\\Infrastructure\\Database::instance()->driver();$sql=$driver==='pgsql'?\"SELECT current_setting('TimeZone') AS tz, 0 AS diff\":'SELECT @@session.time_zone AS tz, TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW()) AS diff';$tz=query($sql)->fetch();echo json_encode([$tz['tz']==='UTC'?'+00:00':$tz['tz'],(int)$tz['diff'],date_default_timezone_get()]);")
        self.assertEqual(json.loads(out),['+00:00',0,'UTC'])
    def test_17_unreachable_database_returns_json_503(self):
        # A session with a user forces a DB query, which must fail cleanly without a second connection attempt.
        out=in_app("session_id('dbfail'.bin2hex(random_bytes(4)));session_start();$_SESSION['uid']=1;session_write_close();$_GET['action']='tickets';$_SERVER['REQUEST_METHOD']='GET';register_shutdown_function(function(){fwrite(STDERR,(string)http_response_code());});include '/var/www/html/api/index.php';",
            env={'DB_HOST':'127.0.0.1','DB_PASSWORD':'wrong'},require_db=False)
        self.assertEqual(json.loads(out),{'error':'Datenbank vorübergehend nicht erreichbar.'})
    def test_18_admin_drinks_need_volume(self):
        suffix=uuid.uuid4().hex[:8];name='Testgetränk '+suffix
        self.assertEqual(self.admin.call('admin_menu',{'name':name,'category':'Tests','station':'bar','price':300})[0],400)
        self.assertEqual(self.admin.call('admin_menu',{'name':name+' 300 ml','category':'Tests','station':'bar','price':300,'volume_ml':300})[0],400)
        self.assertEqual(self.admin.call('admin_menu',{'name':name,'category':'Tests','station':'bar','price':300,'volume_ml':250,'straw':True})[0],200)
        self.assertEqual(self.admin.call('admin_menu',{'name':'Testspeise '+suffix,'category':'Tests','station':'kitchen','price':500,'straw':True})[0],200)
        _,menu=self.admin.call('menu')
        drink=next(m for m in menu if m['name']==name+' 250 ml');food=next(m for m in menu if m['name']=='Testspeise '+suffix)
        self.assertEqual((drink['station'],int(drink['straw'])),('bar',1))
        self.assertEqual(int(food['straw']),0)
        edit={'id':int(drink['id']),'name':name,'category':'Tests','station':'bar','price':420,'volume_ml':400,'straw':False}
        self.assertEqual(self.admin.call('admin_menu',{**edit,'volume_ml':None})[0],400)
        self.assertEqual(self.admin.call('admin_menu',edit)[0],200)
        _,menu=self.admin.call('menu');changed=next(m for m in menu if int(m['id'])==int(drink['id']))
        self.assertEqual((changed['name'],int(changed['price']),int(changed['straw'])),(name+' 400 ml',420,0))
        self.assertEqual(self.admin.call('admin_menu',{**edit,'id':999999})[0],404)
    def test_19_admin_drink_with_sizes(self):
        suffix=uuid.uuid4().hex[:8];name='Testschorle '+suffix
        drink={'name':name,'category':'Tests','straw':True,'sizes':[{'volume_ml':300,'price':350},{'volume_ml':500,'price':520}]}
        self.assertEqual(self.bar.call('admin_drink',drink)[0],403)
        self.assertEqual(self.admin.call('admin_drink',{**drink,'sizes':[]})[0],400)
        self.assertEqual(self.admin.call('admin_drink',{**drink,'sizes':[{'volume_ml':300,'price':350},{'volume_ml':300,'price':400}]})[0],400)
        self.assertEqual(self.admin.call('admin_drink',{**drink,'name':name+' 300 ml'})[0],400)
        code,created=self.admin.call('admin_drink',drink);self.assertEqual(code,200,created)
        small,large=created['ids']
        self.assertEqual(self.admin.call('admin_drink',drink)[0],409)
        sizes=lambda:{m['name']:(int(m['price']),int(m['straw']),int(m['active'])) for m in self.admin.call('menu')[1] if m['name'].startswith('Testschorle') and suffix in m['name']}
        self.assertEqual(sizes(),{name+' 300 ml':(350,1,1),name+' 500 ml':(520,1,1)})
        self.assertEqual(self.admin.call('admin_drink',{**drink,'sizes':[{'id':small,'volume_ml':300,'price':350}]})[0],400)
        renamed='Testsaft '+suffix
        edit={'name':renamed,'category':'Tests','straw':False,'sizes':[{'id':small,'volume_ml':300,'price':360,'active':False},{'id':large,'volume_ml':500,'price':550},{'volume_ml':1000,'price':900}]}
        code,saved=self.admin.call('admin_drink',edit);self.assertEqual(code,200,saved)
        self.assertEqual(saved['ids'][:2],[small,large])
        menu={m['name']:m for m in self.admin.call('menu')[1] if suffix in m['name']}
        self.assertEqual(sorted(menu),[renamed+' 1000 ml',renamed+' 300 ml',renamed+' 500 ml'])
        self.assertEqual((int(menu[renamed+' 300 ml']['id']),int(menu[renamed+' 300 ml']['active']),int(menu[renamed+' 500 ml']['price']),int(menu[renamed+' 1000 ml']['straw'])),(small,0,550,0))
    def test_20_request_size_and_depth_limits(self):
        code,data=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':7,'note':'x'*70000,'items':[{'menu_id':1,'quantity':1,'note':''}]})
        self.assertEqual(code,413,data)
        nested={'a':1}
        for _ in range(10): nested={'a':nested}
        valid={'request_key':str(uuid.uuid4()),'table_id':7,'items':[{'menu_id':1,'quantity':1,'note':''}]}
        self.assertEqual(self.service.call('order',{**valid,'extra':nested})[0],400)
        self.assertEqual(self.service.call('order',valid)[0],201)
    def test_21_active_tickets_are_not_cut_off(self):
        table_id=900+int(uuid.uuid4().hex[:4],16)%99
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Rückstau '+str(table_id)})[0],200)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':True})[0],200)
        last=None
        for _ in range(101):
            code,last=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':1,'quantity':1,'note':''},{'menu_id':6,'quantity':1,'note':''}]})
            self.assertEqual(code,201,last)
        _,data=self.service.call('tickets')
        self.assertGreater(len(data['tickets']),200)
        self.assertEqual(sum(1 for t in data['tickets'] if int(t['order_id'])==last['id']),2)
        _,kitchen=self.kitchen.call('tickets')
        self.assertTrue(any(int(t['order_id'])==last['id'] for t in kitchen['tickets']))
    def test_22_login_ip_throttling_and_cleanup(self):
        # Runs last: it blocks the test client's address and clears the throttling table afterwards.
        old='0'*64
        in_app("query('DELETE FROM login_attempts WHERE identity_hash=?',['"+old+"']);query('INSERT INTO login_attempts(identity_hash,attempts,updated_at) VALUES(?,1,?)',['"+old+"',gmdate('Y-m-d H:i:s',time()-172800)]);")
        try:
            c=Client();codes=[]
            for _ in range(35):
                codes.append(c.call('login',{'username':'spray_'+uuid.uuid4().hex[:10],'password':'bad'})[0])
                if codes[-1]==429: break
            self.assertEqual(codes[-1],429,codes)
            self.assertTrue(all(code==401 for code in codes[:-1]))
            self.assertEqual(Client().call('login',{'username':'mia','password':'Stimbeck-Demo-2026!'})[0],429)
            self.assertEqual(in_app("echo query('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=?',['"+old+"'])->fetchColumn();"),'0')
        finally:
            in_app("query('DELETE FROM login_attempts');")
        Client('mia')  # Anmeldung funktioniert nach dem Aufräumen wieder.
    def test_23_service_covers_unstaffed_bar(self):
        def bar_ticket(menu_id=6):
            code,order=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':7,'items':[{'menu_id':menu_id,'quantity':1,'note':''}]})
            self.assertEqual(code,201,order)
            return order['id'],int(next(t for t in self.service.call('tickets')[1]['tickets'] if int(t['order_id'])==order['id'])['id'])
        _,drink=bar_ticket()
        # Tresen has its view open: service must not work drink tickets.
        self.assertFalse(self.bar.call('tickets&view=bar')[1]['bar_cover'])
        self.assertFalse(self.service.call('tickets&view=service')[1]['bar_cover'])
        self.assertEqual(self.service.call('status',{'id':drink,'status':'working'})[0],403)
        # Tresen presence goes stale: service covers until Abholbereit, the bon stays at the bar station.
        in_app("query('UPDATE users SET last_seen=? WHERE last_view=?',[gmdate('Y-m-d H:i:s',time()-60),'bar']);")
        self.assertTrue(self.service.call('tickets&view=service')[1]['bar_cover'])
        self.assertFalse(self.kitchen.call('tickets&view=kitchen')[1]['bar_cover'])
        for state in ['working','ready','collected']: self.assertEqual(self.service.call('status',{'id':drink,'status':state})[0],200)
        saved=next(t for t in self.service.call('tickets&archive=1')[1]['tickets'] if int(t['id'])==drink)
        self.assertEqual(saved['station'],'bar')
        self.assertEqual([(e['action'],e['name']) for e in saved['events']][1:3],[('working','Mia'),('ready','Mia')])
        _,food=bar_ticket(menu_id=1)
        self.assertEqual(self.service.call('status',{'id':food,'status':'working'})[0],403)
        # Late cancellation of a covered drink: service sees and acknowledges the bar notice.
        order_id,late=bar_ticket()
        self.assertEqual(self.service.call('status',{'id':late,'status':'working'})[0],200)
        self.assertEqual(self.service.call('order_cancel',{'order_id':order_id,'reason':'Gast geht'})[0],200)
        alert=next(a for a in self.service.call('tickets&view=service')[1]['alerts'] if int(a['ticket_id'])==late)
        self.assertEqual(self.service.call('station_alert_ack',{'id':int(alert['id'])})[0],200)
        # Tresen is back: cover ends for new actions.
        _,next_drink=bar_ticket()
        self.bar.call('tickets&view=bar')
        self.assertFalse(self.service.call('tickets&view=service')[1]['bar_cover'])
        self.assertEqual(self.service.call('status',{'id':next_drink,'status':'working'})[0],403)
        self.assertEqual(self.bar.call('status',{'id':next_drink,'status':'working'})[0],200)
    def test_24_separate_payment_per_order(self):
        table_id=800+int(uuid.uuid4().hex[:4],16)%99
        self.assertEqual(self.admin.call('admin_table',{'id':table_id,'name':'Getrennt '+str(table_id)})[0],200)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':True})[0],200)
        def drink_order(quantity):
            code,order=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':table_id,'items':[{'menu_id':6,'quantity':quantity,'note':''}]})
            self.assertEqual(code,201,order)
            ticket=int(next(t for t in self.bar.call('tickets')[1]['tickets'] if int(t['order_id'])==order['id'])['id'])
            return order['id'],ticket
        first,first_ticket=drink_order(1);second,second_ticket=drink_order(2)
        self.assertEqual(self.service.call('order_paid',{'order_id':first,'expected_total':350})[0],409)
        for state in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':first_ticket,'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':first_ticket,'status':'collected'})[0],200)
        self.assertEqual(self.service.call('order_paid',{'order_id':first,'expected_total':999})[0],409)
        code,paid=self.service.call('order_paid',{'order_id':first,'expected_total':350});self.assertEqual(code,200,paid)
        self.assertFalse(paid['visit_closed'])
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        orders={o['id']:o for o in bill['orders']}
        self.assertEqual((bill['total'],bool(orders[first]['paid_at']),orders[first]['can_pay'],orders[second]['can_pay']),(700,True,False,False))
        self.assertEqual(self.service.call('order_paid',{'order_id':first,'expected_total':350})[0],409)
        self.assertEqual(self.service.call('order_change',{'order_id':first,'mode':'note','note':'Neu','reason':'Test','request_key':str(uuid.uuid4())})[0],409)
        self.assertEqual(self.service.call('order_cancel',{'order_id':first,'reason':'Test'})[0],409)
        for state in ['working','ready']: self.assertEqual(self.bar.call('status',{'id':second_ticket,'status':state})[0],200)
        self.assertEqual(self.service.call('status',{'id':second_ticket,'status':'collected'})[0],200)
        code,paid=self.service.call('order_paid',{'order_id':second,'expected_total':700});self.assertEqual(code,200,paid)
        self.assertTrue(paid['visit_closed'])
        _,payments=self.service.call('payments')
        record=next(p for p in payments['paid'] if p['visit_id']==bill['visit_id'])
        self.assertEqual(record['amount'],1050)
        self.assertEqual([e['action'] for e in payments['events'] if e['visit_id']==bill['visit_id']][:2],['order_paid','order_paid'])
        # Reversal reopens the whole visit; the rest can then be paid in one go.
        self.assertEqual(self.service.call('payment_reverse',{'visit_id':bill['visit_id'],'reason':'Falsch kassiert'})[0],200)
        _,bill=self.service.call('table_bill&table_id='+str(table_id))
        self.assertEqual((bill['total'],bill['can_pay']),(1050,True))
        self.assertEqual(self.service.call('table_paid',{'visit_id':bill['visit_id'],'expected_total':1050})[0],200)
        self.assertEqual(self.admin.call('admin_table_status',{'id':table_id,'active':False})[0],200)
    def test_25_kitchen_calls_service(self):
        visible=lambda client:[c for c in client.call('service_calls')[1] if c['station']=='kitchen']
        self.assertEqual(self.service.call('service_call',{'station':'kitchen','note':'x'})[0],403)
        self.assertEqual(self.bar.call('service_call',{'station':'kitchen','note':'x'})[0],403)
        code,call=self.kitchen.call('service_call',{'station':'kitchen','note':'Bitte in die Küche'});self.assertEqual(code,201,call)
        code,again=self.kitchen.call('service_call',{'station':'kitchen','note':'Essen abholen'})
        self.assertEqual((code,again['id'],again['duplicate']),(200,call['id'],True))
        self.assertEqual([(c['id'],c['note'],c['acknowledged_at']) for c in visible(self.service)],[(call['id'],'Essen abholen',None)])
        self.assertEqual([c['id'] for c in visible(self.kitchen)],[call['id']])
        self.assertEqual(visible(self.bar),[])
        self.assertEqual(self.bar.call('service_call_ack',{'id':call['id']})[0],403)
        self.assertEqual(self.service.call('service_call_ack',{'id':call['id']})[0],200)
        self.assertTrue(self.service.call('service_call_ack',{'id':call['id']})[1]['duplicate'])
        self.assertEqual(self.kitchen.call('service_call_cancel',{'id':call['id']})[0],409)
        self.assertEqual(visible(self.kitchen)[0]['acknowledged_by'],'Mia')
        code,second=self.kitchen.call('service_call',{'station':'kitchen','note':'Rückfrage zur Bestellung'});self.assertEqual(code,201,second)
        self.assertEqual(self.kitchen.call('service_call_cancel',{'id':second['id']})[0],200)
        self.assertNotIn(second['id'],[c['id'] for c in visible(self.service)])
        self.assertEqual(self.service.call('service_call_ack',{'id':second['id']})[0],409)
    def test_26_dish_addons(self):
        base={'id':1,'name':'Hamburger mit Bacon','category':'Speisen','station':'kitchen','price':1590}
        addons=lambda:{int(m['id']):[int(x) for x in m['addons']] for m in self.service.call('menu')[1]}
        try:
            self.assertEqual(self.admin.call('admin_menu',{**base,'addons':[3,4]})[0],200)
            self.assertEqual(addons()[1],[3,4])
            self.assertEqual(self.admin.call('admin_menu',{**base,'addons':[6]})[0],400)
            self.assertEqual(self.admin.call('admin_menu',{**base,'addons':[1]})[0],400)
            self.assertEqual(self.admin.call('admin_menu',{**base,'addons':[999999]})[0],400)
            self.assertEqual(self.admin.call('admin_menu',{'id':6,'name':'Apfelschorle','category':'Getränke','station':'bar','price':350,'volume_ml':300,'addons':[3]})[0],400)
            self.assertEqual(self.admin.call('admin_menu',base)[0],200)
            self.assertEqual(addons()[1],[3,4])
            code,created=self.admin.call('admin_menu',{'name':'Testteller '+uuid.uuid4().hex[:6],'category':'Tests','station':'kitchen','price':900,'addons':[4]})
            self.assertEqual(code,200,created);self.assertEqual(addons()[created['id']],[4])
            # Add-ons are ordered as normal positions with their own price.
            code,order=self.service.call('order',{'request_key':str(uuid.uuid4()),'table_id':7,'items':[{'menu_id':1,'quantity':1,'note':''},{'menu_id':3,'quantity':1,'note':'zu Hamburger mit Bacon'}]})
            self.assertEqual(code,201,order)
        finally:
            self.admin.call('admin_menu',{**base,'addons':[]})
        self.assertEqual(addons()[1],[])
    def test_27_dish_quick_wishes(self):
        base={'id':2,'name':'Schnitzel Wiener Art','category':'Speisen','station':'kitchen','price':1690}
        wishes=lambda:next(m['wishes'] for m in self.service.call('menu')[1] if int(m['id'])==2)
        try:
            self.assertEqual(self.admin.call('admin_menu',{**base,'wishes':' Mit Pommes ,Mit Bratkartoffeln,, Mit Pommes\n'})[0],200)
            self.assertEqual(wishes(),'Mit Pommes, Mit Bratkartoffeln')
            self.assertEqual(self.admin.call('admin_menu',base)[0],200)
            self.assertEqual(wishes(),'Mit Pommes, Mit Bratkartoffeln')
            self.assertEqual(self.admin.call('admin_menu',{**base,'wishes':'x'*41})[0],400)
            self.assertEqual(self.admin.call('admin_menu',{**base,'wishes':', '.join('W'+str(i) for i in range(16))})[0],400)
            self.assertEqual(self.admin.call('admin_menu',{'id':6,'name':'Apfelschorle','category':'Getränke','station':'bar','price':350,'volume_ml':300,'wishes':'Ohne Eis'})[0],400)
        finally:
            self.admin.call('admin_menu',{**base,'wishes':''})
        self.assertEqual(wishes(),'')
def in_app(code,env=None,require_db=True):
    """Run PHP inside the local app container; skipped when the Docker stack is not available."""
    if urllib.parse.urlparse(TEST_URL).hostname not in ('127.0.0.1','localhost') or not shutil.which('docker'): raise unittest.SkipTest('Nur gegen den lokalen Docker-Teststack.')
    prefix="require '/var/www/html/api/db.php';" if require_db else ''
    args=['docker','compose','-p',os.environ.get('COMPOSE_PROJECT_NAME','kittchenapp-test'),'exec','-T']+[f'-e{k}={v}' for k,v in (env or {}).items()]+['app','php','-r',prefix+code]
    result=subprocess.run(args,cwd=os.path.dirname(os.path.dirname(os.path.abspath(__file__))),capture_output=True,text=True,timeout=30)
    return result.stdout
if __name__=='__main__': unittest.main(verbosity=2)
