"""Isolated Windows/MySQL + real HTTP + headless Chrome tests. No production writes.

Requires Python 3 with Pillow, PHP 8.4+, MySQL 8.4+ client/server, Node and Playwright.
Verified with PHP 8.4.28 + MySQL 8.4.9 and with PHP 8.6.0 + MySQL 26.10.0.
All temporary credentials travel through stdin or an external temporary config.
"""
import argparse
import hashlib
import http.cookiejar
import io
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import stat
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from PIL import Image, PngImagePlugin

ROOT = Path(__file__).resolve().parent.parent
ARTIFACTS = ROOT / 'tests' / 'artifacts'
REPORT = {'checks': {}, 'cleanup': {}, 'browser': None}
HIDDEN = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0


def check(name, condition):
    REPORT['checks'][name] = 'PASS' if condition else 'FAIL'
    print(('PASS ' if condition else 'FAIL ') + name, flush=True)
    if not condition:
        raise AssertionError(name)


def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def run(args, data='', env=None, timeout=45):
    result = subprocess.run(args, input=data.encode(), stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE, cwd=ROOT, env=env,
                            timeout=timeout, creationflags=HIDDEN)
    if result.returncode:
        raise RuntimeError('Child command failed: ' + Path(args[0]).name)
    return result.stdout.decode('utf-8-sig')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(NoRedirect, urllib.request.HTTPCookieProcessor(self.jar))

    def request(self, path, fields=None, file=None, method=None, headers=None, body=None):
        headers = dict(headers or {})
        if file is not None:
            boundary = 'TideBitesQA' + secrets.token_hex(12)
            parts = []
            for key, value in fields.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            filename, mime, content = file
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n')
            parts.append(f'--{boundary}--\r\n'.encode())
            body = b''.join(parts)
            headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        elif fields is not None:
            body = urllib.parse.urlencode(fields).encode()
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        req = urllib.request.Request(self.base + path, data=body, headers=headers, method=method)
        try:
            response = self.opener.open(req, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.headers, response.read()

    def oversized_post_status(self, path, size):
        """Status for a POST declaring `size` bytes. The PHP server can answer 413 and close
        while a client is still uploading; on Windows the reset then discards the reply
        (WinError 10053). Like curl, send `Expect: 100-continue` and upload only if asked."""
        parsed = urllib.parse.urlsplit(self.base)
        cookies = '; '.join(f'{c.name}={c.value}' for c in self.jar)
        head = (f'POST {path} HTTP/1.1\r\nHost: {parsed.netloc}\r\n'
                f'Content-Type: application/octet-stream\r\nContent-Length: {size}\r\n'
                'Expect: 100-continue\r\nConnection: close\r\n'
                + (f'Cookie: {cookies}\r\n' if cookies else '') + '\r\n')
        with socket.create_connection((parsed.hostname, parsed.port), timeout=15) as sock:
            sock.sendall(head.encode())
            status = self._read_status(sock)
            if status == 100:
                try:
                    sock.sendall(b'x' * size)
                except (ConnectionAbortedError, ConnectionResetError, BrokenPipeError):
                    return 0
                status = self._read_status(sock)
            return status

    @staticmethod
    def _read_status(sock):
        data = b''
        while b'\r\n\r\n' not in data:
            chunk = sock.recv(4096)
            if not chunk:
                break
            data += chunk
        match = re.match(rb'HTTP/1\.[01] (\d{3})', data)
        return int(match.group(1)) if match else 0

    def sid(self):
        return next((c.value for c in self.jar if c.name == 'TIDEBITES_ADMIN'), '')


def field(html, name):
    match = re.search(r'name="' + re.escape(name) + r'" value="([^"]+)"', html.decode())
    if not match:
        raise AssertionError('Missing form field ' + name)
    return match.group(1)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--node-modules', default='')
    parser.add_argument('--browser', default=r'C:\Program Files\Google\Chrome\Application\chrome.exe')
    args = parser.parse_args()
    ARTIFACTS.mkdir(parents=True, exist_ok=True)
    temp = Path(tempfile.mkdtemp(prefix='tidebites-admin-qa-'))
    data_dir = temp / 'mysql'
    runtime = temp / 'runtime'
    config = temp / 'database.php'
    suffix = secrets.token_hex(6)
    database = 'tbadmin' + suffix
    credentials = {'host': '127.0.0.1', 'port': port(), 'root_password': secrets.token_hex(24),
                   'database': database, 'app_user': 'tbadmin_' + suffix,
                   'app_password': secrets.token_hex(24), 'admin_password': secrets.token_hex(24),
                   'config': str(config)}
    env = dict(os.environ, TIDEBITES_CONFIG=str(config), TIDEBITES_ADMIN_RUNTIME=str(runtime))
    php = shutil.which('php')
    mysql = shutil.which('mysql')
    mysqld = shutil.which('mysqld')
    node = shutil.which('node')
    if not all([php, mysql, mysqld, node]):
        raise RuntimeError('PHP, MySQL server/client and Node must be on PATH.')
    processes = []
    handles = []
    original_uploads = set((ROOT / 'uploads' / 'foods').glob('tb-*'))
    original_assets = {p.relative_to(ROOT).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
                       for p in (ROOT / 'assets').rglob('*') if p.is_file()}
    baseline = None
    setup = False

    def fixture(action, extra=None, target_env=env):
        payload = dict(credentials)
        payload.update(extra or {})
        return json.loads(run([php, 'tests/admin-fixture.php', action], json.dumps(payload), target_env))

    def spawn(command, label, target_env=None):
        log = open(temp / (label + '.log'), 'wb')
        handles.append(log)
        proc = subprocess.Popen(command, cwd=ROOT, env=target_env, stdout=log, stderr=log, creationflags=HIDDEN)
        processes.append(proc)
        return proc

    def clear_limit():
        (runtime / 'login-attempts.json').write_text('{}', encoding='utf-8')

    def login(client):
        clear_limit()
        response = client.request('/admin/login.php')
        csrf = field(response[2], 'csrf')
        old = client.sid()
        result = client.request('/admin/login.php', {'csrf': csrf, 'username': 'qa_admin', 'password': credentials['admin_password']})
        check('login_success_' + str(len(REPORT['checks'])), result[0] == 303 and result[1]['Location'] == '/admin/foods/index.php')
        check('session_rotated_' + str(len(REPORT['checks'])), client.sid() != old and bool(client.sid()))
        return client

    try:
        baseline = fixture('baseline', target_env=os.environ.copy())
        check('production_connection_read_only', all(baseline[table]['count'] >= 0 and len(baseline[table]['digest']) == 64 for table in ['food_items', 'admins', 'orders', 'order_items']))
        run([mysqld, '--no-defaults', '--initialize-insecure', '--datadir=' + str(data_dir)], timeout=60)
        memory_name = 'TideBitesQA' + suffix
        common = [mysqld, '--no-defaults', '--datadir=' + str(data_dir), '--mysqlx=0', '--skip-log-bin',
                  '--performance-schema=OFF', '--innodb-buffer-pool-size=32M', '--innodb-redo-log-capacity=16M']
        first = spawn(common + ['--skip-networking', '--shared-memory', '--shared-memory-base-name=' + memory_name], 'mysql-bootstrap')
        ready = False
        for _ in range(100):
            try:
                run([mysql, '--no-defaults', '--protocol=MEMORY', '--shared-memory-base-name=' + memory_name, '--user=root', '--batch'], 'SELECT 1;', timeout=2)
                ready = True
                break
            except (RuntimeError, subprocess.TimeoutExpired):
                if first.poll() is not None:
                    raise RuntimeError('Isolated MySQL could not start.')
                time.sleep(.15)
        check('isolated_mysql_shared_memory_ready', ready)
        run([mysql, '--no-defaults', '--protocol=MEMORY', '--shared-memory-base-name=' + memory_name, '--user=root', '--batch'],
            "ALTER USER 'root'@'localhost' IDENTIFIED BY '" + credentials['root_password'] + "'; SHUTDOWN;")
        first.wait(timeout=20)
        db_process = spawn(common + ['--bind-address=127.0.0.1', '--port=' + str(credentials['port'])], 'mysql-test')
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', credentials['port']), timeout=.5):
                    break
            except OSError:
                time.sleep(.15)
        check('isolated_database_setup', fixture('setup')['result'] == 'PASS')
        setup = True
        regression = json.loads(run([php, 'database/manage.php', 'test'], json.dumps(credentials), env, timeout=60))
        REPORT['database_regression'] = regression
        check('pr1_59_regressions', regression['result'] == 'PASS' and len(regression['checks']) == 59)
        check('pr1_cleanup', all(v.startswith('REMOVED') for v in regression['cleanup'].values()))
        http_port = port()
        server = spawn([php, '-d', 'display_errors=0', '-d', 'log_errors=0', '-S', f'127.0.0.1:{http_port}', '-t', '.', 'router.php'], 'php', env)
        base = f'http://127.0.0.1:{http_port}'
        guest = Client(base)
        for _ in range(60):
            try:
                if guest.request('/index.html')[0] == 200:
                    break
            except OSError:
                time.sleep(.1)
        check('homepage_unchanged', guest.request('/index.html')[2] == (ROOT / 'index.html').read_bytes())
        static_paths = ['css/style.css', 'js/script.js'] + [p for p in original_assets if not Path(p).name.startswith('.')]
        for path in static_paths:
            status, headers, body = guest.request('/' + path, method='HEAD' if path.endswith('.mp3') else 'GET')
            expected = (ROOT / path).read_bytes()
            check('static_' + path, status == 200 and (int(headers['Content-Length']) == len(expected) if path.endswith('.mp3') else body == expected))
        protected = ['/database/tidebites.sql', '/config/database.example.php', '/database/manage.ps1', '/database/manage.php/install',
                     '/docs/database-contract.md', '/docs/admin-system.md', '/.git/config', '/.env', '/.htaccess', '/start.ps1', '/start.bat',
                     '/router.php', '/tests/admin-fixture.php', '/admin/includes/auth.php', '/admin/includes/header.php',
                     '/DATABASE/tidebites.sql', '/config%2fdatabase.php', '/%64atabase/tidebites.sql', '/database./tidebites.sql',
                     '/database%20/tidebites.sql', '/database%5Ctidebites.sql', '/%252e%252e/config/database.php',
                     '/uploads/foods/.gitkeep', '/uploads/foods/not-there.php', '/admin/login.php/extra', '/unknown/path', '/css/style.css:stream']
        for path in protected:
            check('http_protected_' + path, guest.request(path)[0] == 404)
        for path in ['/admin/', '/admin/index.php', '/admin/foods/index.php', '/admin/foods/create.php', '/admin/foods/edit.php?id=1', '/admin/foods/delete.php?id=1']:
            response = guest.request(path)
            check('guest_get_' + path, response[0] == 303 and response[1]['Location'] == '/admin/login.php')
        for path in ['create.php', 'edit.php?id=1', 'delete.php', 'status.php']:
            check('guest_post_' + path, guest.request('/admin/foods/' + path, {'id': '1'})[0] == 401)
        check('get_logout_denied', guest.request('/admin/logout.php')[0] == 405)
        check('get_status_denied', guest.request('/admin/foods/status.php?id=1&is_active=0')[0] == 405)
        response = guest.request('/admin/login.php')
        cookie = response[1].get('Set-Cookie', '')
        # A fresh cookie is issued by a fresh client; existing requests need not resend it.
        fresh = Client(base)
        fresh_response = fresh.request('/admin/login.php')
        cookie = fresh_response[1].get('Set-Cookie', '')
        check('cookie_http_properties', 'HttpOnly' in cookie and 'SameSite=Strict' in cookie and 'path=/admin/' in cookie and 'Secure' not in cookie)
        csrf = field(response[2], 'csrf')
        check('login_csrf_required', guest.request('/admin/login.php', {'username': 'qa_admin', 'password': credentials['admin_password']})[0] == 403)
        for username, password in [('', ''), ("' OR 1=1 --", 'x' * 20), ('qa_admin', 'incorrect-password'), ('not_existing', 'incorrect-password')]:
            clear_limit()
            result = guest.request('/admin/login.php', {'csrf': csrf, 'username': username, 'password': password})
            check('invalid_login_' + str(len(REPORT['checks'])), result[0] == 422 and b'Unable to sign in.' in result[2])
        clear_limit()
        for _ in range(5):
            guest.request('/admin/login.php', {'csrf': csrf, 'username': 'qa_admin', 'password': 'incorrect-password'})
        cookieless = Client(base)
        token = field(cookieless.request('/admin/login.php')[2], 'csrf')
        blocked = cookieless.request('/admin/login.php', {'csrf': token, 'username': 'QA_ADMIN', 'password': credentials['admin_password']})
        check('login_limit_survives_new_cookie_and_case', blocked[0] == 429 and blocked[1]['Retry-After'] == '900')
        admin = login(Client(base))
        listing = admin.request('/admin/foods/index.php')
        check('database_list', listing[0] == 200 and listing[2].count(b'data-food-id=') == 10)
        token = field(listing[2], 'csrf')
        for endpoint in ['create.php', 'edit.php?id=1', 'delete.php', 'status.php']:
            for csrf_value in ['', 'wrong']:
                check('csrf_' + endpoint + '_' + (csrf_value or 'missing'), admin.request('/admin/foods/' + endpoint, {'csrf': csrf_value, 'id': '1'})[0] == 403)
            check('put_' + endpoint, admin.request('/admin/foods/' + endpoint, method='PUT')[0] == 405)
        for path in ['edit.php?id=999999', 'delete.php?id=999999', 'edit.php?id[]=1', 'edit.php?id=1%20OR%201=1']:
            check('invalid_id_' + path, admin.request('/admin/foods/' + path)[0] == 404)
        image_bytes = {}
        for fmt in ['PNG', 'JPEG', 'WEBP']:
            buf = io.BytesIO()
            Image.new('RGB', (80, 60), (20, 110, 150)).save(buf, format=fmt)
            image_bytes[fmt] = buf.getvalue()
        image = ('qa.png', 'image/png', image_bytes['PNG'])

        def form():
            page = admin.request('/admin/foods/create.php')[2]
            return {'csrf': field(page, 'csrf'), 'form_token': field(page, 'form_token'), 'name': 'QA ocean bowl',
                    'description': 'QA meal description', 'price': '123', 'category': 'meals', 'image_alt': 'Blue test image', 'sort_order': '20', 'is_active': '1'}

        values = form()
        before = fixture('snapshot')
        invalid_values = {'name': ['', ' ' * 4, 'x' * 151], 'description': ['', '🌊' * 17000], 'price': ['-1', '1.25', '1e2', '100000000', '0.001', 'NaN'],
                          'category': ['MEALS', 'snacks'], 'sort_order': ['-1', '65536', '1.2'], 'is_active': ['2', 'true'], 'image_alt': ['', 'x' * 501]}
        for key, variants in invalid_values.items():
            for variant in variants:
                bad = dict(values, **{key: variant})
                response = admin.request('/admin/foods/create.php', bad, image)
                check('field_validation_' + key + '_' + str(len(REPORT['checks'])), response[0] == 422)
        check('missing_image_rejected', admin.request('/admin/foods/create.php', values)[0] == 422)
        bad_files = [('evil.php', 'image/png', image_bytes['PNG']), ('fake.jpg', 'image/png', image_bytes['PNG']),
                     ('evil.png', 'image/png', b'<?php echo "executable"; ?>'), ('fake.svg', 'image/svg+xml', b'<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
                     ('../escape.png', 'image/png', image_bytes['PNG']), ('huge.png', 'image/png', b'x' * 2097153),
                     ('broken.png', 'image/png', image_bytes['PNG'][:40])]
        buf = io.BytesIO(); Image.new('RGB', (6001, 1)).save(buf, format='PNG')
        bad_files.append(('wide.png', 'image/png', buf.getvalue()))
        for bad_file in bad_files:
            response = admin.request('/admin/foods/create.php', values, bad_file)
            check('upload_rejected_' + bad_file[0], response[0] == 422)
        check('post_limit_rejected', admin.oversized_post_status('/admin/foods/create.php', 8500000) == 413)
        check('invalid_requests_no_db_changes', fixture('snapshot') == before)
        fixture('fault', {'enabled': True, 'kind': 'insert'})
        files_before = set((ROOT / 'uploads/foods').glob('tb-*'))
        check('insert_failure_general_response', admin.request('/admin/foods/create.php', values, image)[0] == 503)
        check('insert_failure_no_row_or_orphan', fixture('snapshot') == before and set((ROOT / 'uploads/foods').glob('tb-*')) == files_before)
        fixture('fault', {'enabled': False})
        response = admin.request('/admin/foods/create.php', values, image)
        check('create_real_mysql', response[0] == 303)
        created = fixture('snapshot')['foods'][-1]
        food_id = created['id']
        old_path = created['image_path']
        check('created_values', created['name'] == values['name'] and created['price'] == '123.00' and created['category'] == 'meals' and created['sort_order'] == 20 and created['is_active'] == 1)
        check('generated_image_path', bool(re.fullmatch(r'uploads/foods/tb-[a-f0-9]{32}\.png', old_path)) and (ROOT / old_path).read_bytes() == image_bytes['PNG'])
        check('public_upload_mime', guest.request('/' + old_path)[1]['Content-Type'] == 'image/png')
        check('upload_post_forbidden', guest.request('/' + old_path, {})[0] == 405)
        check('create_replay_blocked', admin.request('/admin/foods/create.php', values, image)[0] == 409)
        check('refresh_is_read_only', admin.request('/admin/foods/index.php')[0] == 200 and len(fixture('snapshot')['foods']) == 11)
        fixture('order', {'id': food_id})
        history = fixture('snapshot')['items'][0]
        edited = dict(values, name='<script>alert("xss")</script> 海味', description="O'Reilly x'); DROP TABLE food_items; -- <img src=x onerror=alert(1)>",
                      price='0', category='desserts', image_alt='" onerror="alert(1)', sort_order='65535', is_active='0')
        check('edit_all_fields_keep_image', admin.request(f'/admin/foods/edit.php?id={food_id}', edited)[0] == 303)
        after = fixture('snapshot')
        updated = after['foods'][-1]
        check('edit_persisted_exact_literals', updated['name'] == edited['name'] and updated['description'] == edited['description'] and updated['image_path'] == old_path and updated['price'] == '0.00' and updated['category'] == 'desserts' and updated['image_alt'] == edited['image_alt'] and updated['sort_order'] == 65535 and updated['is_active'] == 0)
        check('edit_keeps_order_snapshot', after['items'][0] == history)
        html = admin.request('/admin/foods/index.php')[2]
        check('xss_output_escaped', b'<script>alert' not in html and b'&lt;script&gt;alert' in html and b'&quot; onerror=&quot;' in html)
        check('search_bound_sql', admin.request('/admin/foods/index.php?q=' + urllib.parse.quote("' OR 1=1 --"))[2].count(b'data-food-id=') == 0)
        check('search_literal_wildcards', admin.request('/admin/foods/index.php?q=%25')[2].count(b'data-food-id=') == 0)
        filtered = admin.request('/admin/foods/index.php?category=desserts&status=0')[2]
        check('filters_database', filtered.count(b'data-food-id=') == 1)
        for status in ['1', '0', '1']:
            check('availability_' + str(len(REPORT['checks'])), admin.request('/admin/foods/status.php', {'csrf': token, 'id': food_id, 'is_active': status})[0] == 303 and fixture('snapshot')['foods'][-1]['is_active'] == int(status))
        check('invalid_availability', admin.request('/admin/foods/status.php', {'csrf': token, 'id': food_id, 'is_active': '7'})[0] == 422)
        check('missing_food_status', admin.request('/admin/foods/status.php', {'csrf': token, 'id': '999999', 'is_active': '0'})[0] == 404)
        # Trigger fails the actual SQL update after a real HTTP image upload.
        fixture('fault', {'enabled': True})
        files_before = set((ROOT / 'uploads/foods').glob('tb-*'))
        data_before = fixture('snapshot')
        failed = admin.request(f'/admin/foods/edit.php?id={food_id}', edited, ('replacement.jpg', 'image/jpeg', image_bytes['JPEG']))
        check('db_failure_general_response', failed[0] == 503 and b'QA forced failure' not in failed[2] and b'SQLSTATE' not in failed[2])
        check('db_failure_preserves_original', fixture('snapshot') == data_before and (ROOT / old_path).exists())
        check('db_failure_cleans_new_upload', set((ROOT / 'uploads/foods').glob('tb-*')) == files_before)
        fixture('fault', {'enabled': False})
        check('failed_replacement_keeps_image', admin.request(f'/admin/foods/edit.php?id={food_id}', edited, ('invalid.jpg', 'image/jpeg', b'bad'))[0] == 422 and fixture('snapshot') == data_before)
        check('replace_with_jpeg', admin.request(f'/admin/foods/edit.php?id={food_id}', edited, ('new.jpeg', 'image/jpeg', image_bytes['JPEG']))[0] == 303)
        jpeg_path = fixture('snapshot')['foods'][-1]['image_path']
        check('old_upload_removed_after_commit', not (ROOT / old_path).exists() and (ROOT / jpeg_path).exists())
        # Simulate a shared reference in the isolated DB only.
        fixture('shared-image', {'id': 1, 'path': jpeg_path})
        check('replace_with_webp', admin.request(f'/admin/foods/edit.php?id={food_id}', edited, ('new.webp', 'image/webp', image_bytes['WEBP']))[0] == 303)
        webp_path = fixture('snapshot')['foods'][-1]['image_path']
        check('shared_image_retained', (ROOT / jpeg_path).exists())
        check('get_delete_is_confirmation_only', admin.request(f'/admin/foods/delete.php?id={food_id}')[0] == 200 and fixture('snapshot')['foods'][-1]['id'] == food_id)
        check('delete_requires_confirmation', admin.request('/admin/foods/delete.php', {'csrf': token, 'id': food_id})[0] == 422)
        check('real_delete', admin.request('/admin/foods/delete.php', {'csrf': token, 'id': food_id, 'confirm': 'delete'})[0] == 303)
        deleted = fixture('snapshot')
        check('deleted_row_removed', all(row['id'] != food_id for row in deleted['foods']))
        check('history_survives_delete', deleted['items'][0] == dict(history, food_item_id=None) and deleted['orders'][0]['total_amount'] == '123.00')
        check('deleted_image_removed', not (ROOT / webp_path).exists())
        check('duplicate_delete_404', admin.request('/admin/foods/delete.php', {'csrf': token, 'id': food_id, 'confirm': 'delete'})[0] == 404)
        # Delete the other reference, proving shared uploads are removed only at last use.
        check('last_reference_deleted', admin.request('/admin/foods/delete.php', {'csrf': token, 'id': '1', 'confirm': 'delete'})[0] == 303 and not (ROOT / jpeg_path).exists())
        check('original_asset_delete', admin.request('/admin/foods/delete.php', {'csrf': token, 'id': '2', 'confirm': 'delete'})[0] == 303)
        check('asset_cleanup_refused', fixture('cleanup-check', {'path': 'assets/images/chicken-oyakodon-bowl.jpg'})['result'] and (ROOT / 'assets/images/chicken-oyakodon-bowl.jpg').exists())
        check('traversal_cleanup_refused', fixture('cleanup-check', {'path': 'uploads/foods/../../assets/images/icon.png'})['result'] and (ROOT / 'assets/images/icon.png').exists())
        metadata = PngImagePlugin.PngInfo()
        metadata.add_text('comment', '<?php echo "QA_CODE_EXECUTED"; ?>')
        buf = io.BytesIO(); Image.new('RGB', (80, 60), (20, 110, 150)).save(buf, format='PNG', pnginfo=metadata)
        polyglot = buf.getvalue()
        extra_values = form()
        check('image_metadata_upload', admin.request('/admin/foods/create.php', extra_values, ('metadata.png', 'image/png', polyglot))[0] == 303)
        extra_food = fixture('snapshot')['foods'][-1]
        response = guest.request('/' + extra_food['image_path'])
        check('uploaded_content_never_php_executed', response[0] == 200 and response[1]['Content-Type'] == 'image/png' and response[1]['X-Content-Type-Options'] == 'nosniff' and response[2] == polyglot)
        if os.name == 'nt':
            readonly = ROOT / extra_food['image_path']
            readonly.chmod(stat.S_IREAD)
            try:
                response = admin.request(f"/admin/foods/edit.php?id={extra_food['id']}", extra_values, image)
                check('cleanup_failure_keeps_successful_update', response[0] == 303 and readonly.exists() and fixture('snapshot')['foods'][-1]['image_path'] != extra_food['image_path'])
                check('cleanup_failure_shows_warning', b'could not be removed' in admin.request('/admin/foods/index.php')[2])
            finally:
                readonly.chmod(stat.S_IREAD | stat.S_IWRITE)
                readonly.unlink()
        admin.request('/admin/foods/delete.php', {'csrf': token, 'id': extra_food['id'], 'confirm': 'delete'})
        old_sid = admin.sid()
        check('logout_csrf_required', admin.request('/admin/logout.php', {'csrf': 'wrong'})[0] == 403)
        check('logout_post_success', admin.request('/admin/logout.php', {'csrf': token})[0] == 303)
        check('logout_revokes_access', admin.request('/admin/foods/index.php')[0] == 303 and not (runtime / 'sessions' / ('sess_' + old_sid)).exists())
        replay = Client(base)
        check('old_cookie_unusable', replay.request('/admin/foods/status.php', {'csrf': token, 'id': '3', 'is_active': '0'}, headers={'Cookie': 'TIDEBITES_ADMIN=' + old_sid})[0] == 401)
        for mode in ['invalid', 'expired', 'absolute']:
            client = login(Client(base))
            fixture('session', {'id': client.sid(), 'runtime': str(runtime), 'mode': mode})
            check('session_' + mode + '_denied', client.request('/admin/foods/index.php')[0] == 303)
        fixture('reset-menu')
        order_client = login(Client(base))
        fixture('orders-fixture', {'count': 0})
        check('orders_empty_state', b'No orders yet' in order_client.request('/admin/orders/index.php')[2])
        for endpoint in ['/admin/orders/index.php', '/admin/orders/detail.php?id=1']:
            check('orders_guest_' + endpoint, Client(base).request(endpoint)[0] == 303)
            check('orders_post_refused_' + endpoint, order_client.request(endpoint, {})[0] == 405)
        order_ids = fixture('orders-fixture', {'count': 23, 'attack': True})['ids']
        order_list = order_client.request('/admin/orders/index.php')
        check('orders_paginated_twenty', order_list[0] == 200 and order_list[2].count(b'data-order-id=') == 20)
        check('orders_next_page', order_client.request('/admin/orders/index.php?page=2')[2].count(b'data-order-id=') == 3)
        check('orders_newest_id_first', re.findall(rb'data-order-id="(\d+)"', order_list[2])[0] == str(order_ids[-1]).encode())
        detail = order_client.request('/admin/orders/detail.php?id=' + str(order_ids[0]))
        check('orders_null_fields', detail[0] == 200 and detail[2].count(b'Not provided') == 2 and b'No notes provided.' in detail[2])
        check('orders_snapshot_after_price_change_delete', b'Ocean Bowl (ordered)' in detail[2] and b'NT$130' in detail[2] and b'NT$259' in detail[2] and b'NT$999' not in detail[2])
        check('orders_stored_totals', all(x in detail[2] for x in [b'NT$358', b'NT$36', b'NT$322', b'NT$99']) and b'.00' not in detail[2])
        check('orders_pickup_time_null', b'Not scheduled' in detail[2])
        check('orders_taipei_time', b'08:30' in detail[2] and b'Asia/Taipei' in detail[2])
        attack_detail = order_client.request('/admin/orders/detail.php?id=' + str(order_ids[1]))[2]
        check('orders_xss_escaped', b'&lt;script&gt;alert(1)&lt;/script&gt;' in attack_detail and b'<script>alert(1)' not in attack_detail)
        for order_id in ['0', '999999', '1%20OR%201=1', '1.2', '-1']:
            check('orders_invalid_id_' + order_id, order_client.request('/admin/orders/detail.php?id=' + order_id)[0] == 404)
        check('orders_array_id', order_client.request('/admin/orders/detail.php?id[]=1')[0] == 404)
        check('orders_search_injection', b'No matching orders' in order_client.request('/admin/orders/index.php?q=' + urllib.parse.quote("' OR 1=1 --"))[2])
        check('orders_search_literal_percent', b'No matching orders' in order_client.request('/admin/orders/index.php?q=%25')[2])
        id_search = order_client.request('/admin/orders/index.php?q=' + str(order_ids[-1]))[2]
        check('orders_search_id', ('data-order-id="' + str(order_ids[-1]) + '"').encode() in id_search)
        check('orders_search_phone', order_client.request('/admin/orders/index.php?q=0912')[2].count(b'data-order-id=') == 20)
        orders_before = fixture('snapshot')
        order_client.request('/admin/orders/index.php')
        order_client.request('/admin/orders/detail.php?id=' + str(order_ids[0]))
        check('order_pages_read_only', fixture('snapshot') == orders_before)
        order_ids = fixture('orders-fixture', {'count': 3})['ids']
        clear_limit()
        browser_env = env.copy()
        if args.node_modules:
            browser_env['NODE_PATH'] = args.node_modules
        browser_payload = {'base': base, 'username': 'qa_admin', 'password': credentials['admin_password'],
                           'artifacts': str(ARTIFACTS), 'browser': args.browser,
                           'image': str(ROOT / 'assets/images/mango-snow-ice.jpg'), 'orderId': order_ids[0]}
        browser_output = run([node, 'tests/admin-browser.cjs'], json.dumps(browser_payload), browser_env, timeout=600)
        REPORT['browser'] = json.loads(browser_output)
        check('headless_browser_rwd_and_operations', REPORT['browser']['result'] == 'PASS')
        launcher = json.loads(run(['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'tests/test-launcher.ps1'], env=env, timeout=45))
        REPORT['launcher'] = launcher
        check('launcher_ready_protected_and_stopped', launcher['result'] == 'PASS' and launcher['server_stopped'])
        check('original_assets_unchanged', all(hashlib.sha256((ROOT / p).read_bytes()).hexdigest() == digest for p, digest in original_assets.items()))
        check('production_rows_unchanged', fixture('baseline', target_env=os.environ.copy()) == baseline)
        REPORT['result'] = 'PASS'
    except Exception as error:
        REPORT['result'] = 'FAIL'
        REPORT['error'] = str(error)
        print('FAIL: ' + str(error), flush=True)
    finally:
        if setup:
            try:
                cleanup = fixture('teardown')
                REPORT['cleanup'].update(cleanup)
            except Exception:
                REPORT['cleanup']['database'] = 'CLEANUP_FAILED'
        if setup:
            try:
                fixture('shutdown')
                # Windows mysqld has a supervising parent and a child owning the data files.
                for _ in range(100):
                    try:
                        with socket.create_connection(('127.0.0.1', credentials['port']), timeout=.2):
                            time.sleep(.1)
                    except OSError:
                        break
                time.sleep(.5)
            except Exception:
                REPORT['cleanup']['mysql_shutdown'] = 'CLEANUP_FAILED'
        for proc in reversed(processes):
            if proc.poll() is None:
                proc.terminate()
                try:
                    proc.wait(timeout=20)
                except subprocess.TimeoutExpired:
                    proc.kill(); proc.wait(timeout=10)
        REPORT['cleanup']['processes'] = 'STOPPED' if all(p.poll() is not None for p in processes) else 'CLEANUP_FAILED'
        for handle in handles:
            handle.close()
        for upload in set((ROOT / 'uploads/foods').glob('tb-*')) - original_uploads:
            if upload.parent.resolve() == (ROOT / 'uploads/foods').resolve() and re.fullmatch(r'tb-[a-f0-9]{32}\.(jpg|png|webp)', upload.name):
                upload.unlink()
        REPORT['cleanup']['uploads'] = 'REMOVED_AND_VERIFIED' if set((ROOT / 'uploads/foods').glob('tb-*')) == original_uploads else 'CLEANUP_FAILED'
        if temp.resolve().parent == Path(tempfile.gettempdir()).resolve() and temp.name.startswith('tidebites-admin-qa-'):
            for attempt in range(30):
                try:
                    shutil.rmtree(temp)
                    break
                except OSError:
                    time.sleep(.2)
        REPORT['cleanup']['temporary_files_sessions_config'] = 'REMOVED_AND_VERIFIED' if not temp.exists() else 'CLEANUP_FAILED'
        if 'CLEANUP_FAILED' in REPORT['cleanup'].values():
            REPORT['result'] = 'FAIL'
        (ARTIFACTS / 'report.json').write_text(json.dumps(REPORT, indent=2, ensure_ascii=False), encoding='utf-8')
        print(json.dumps({'result': REPORT['result'], 'checks': len(REPORT['checks']), 'cleanup': REPORT['cleanup']}, indent=2), flush=True)
    return 0 if REPORT['result'] == 'PASS' else 1


if __name__ == '__main__':
    raise SystemExit(main())
