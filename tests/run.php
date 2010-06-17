<?php

require_once 'lime.php';
require_once dirname(__FILE__).'/../pikotan.php';

$t = new lime_test();

$t->diag('function dispatch test');

$t->isnt(dispatch(), 'not ok', 'テーブルが登録されてないときに何もパスを入力してなければエラーを返す');

$dispatched = dispatch('/hoge');
$t->is($dispatched['action'], 'hoge', 'パスを入力していれば、それを整形したパスが返る("/hoge => hoge")');
$t->is($dispatched['method'], null, 'アクション内のメソッド名はなければnullを返す');
$t->is($dispatched['accept'], array('GET', 'POST'), '特に指定がなければ、GETとPOSTを許可する');

register_dispatch(array(
  '/hoge/:id/:method' => 'hoge_action',
  '/fuga' => array(array('method' => 'fuga', 'action' => 'piyo'),
                   array('method' => 'fuga_put', 'action' => 'piyo', 'accept' => array('PUT'))),
));
$_SERVER['REQUEST_METHOD'] = 'GET';
$dispatched = dispatch('/hoge/23/ping');
$t->is($dispatched['action'], 'hoge_action', '登録したパスとマッチしたので、登録されていたアクション名を返す("/hoge/23/ping => hoge_action")');
$t->is(get_param('id'), 23, '"/hoge/23/ping"から、"id=23"を抽出');
$t->is($dispatched['method'], 'ping', '"/hoge/23/ping"から、"method=ping"を抽出');

$_REQUEST = array();
$dispatched = dispatch('/fuga');
$t->is($dispatched['action'], 'piyo', '登録したパスとマッチしたので、登録されていたアクション名を返す("GET /fuga => piyo")');
$t->is($dispatched['method'], 'fuga', '"/fuga"から、"method=fuga"を抽出');

$dispatched = dispatch('/fuga/aaaa');
$t->is($dispatched['action'], 'fuga/aaaa', '"/fuga/aaaa"は登録されていないので、"fuga/aaaa"をアクション名として返す');
register_dispatch(array('/fuga/:id' => 'fuga_action2'));
$dispatched = dispatch('/fuga/aaaa');
$t->is($dispatched['action'], 'fuga_action2', '"/fuga/:id"を登録したので、"/fuga/aaaa"に対して"fuga_action2"をアクション名として返す');

$dispatched = dispatch('/fuga');
$t->is($dispatched['action'], 'piyo', '"/fuga"のアクション名は"piyo"');
$t->is($dispatched['method'], 'fuga', '"/fuga"のメソッド名は"fuga"');

register_dispatch(array('/:action/:method' => array()));
$dispatched = dispatch('/hoge/fuga');
$t->is($dispatched['action'], 'hoge', '"/:action/:method"で登録したので、"/hoge/fuga"のアクション名は"hoge"');
$t->is($dispatched['method'], 'fuga', '"/:action/:method"で登録したので、"/hoge/fuga"のメソッド名は"fuga"');

$_REQUEST = array();
register_dispatch(array(
  '/hoge/:id/:method' => 'hoge_action',
  '/fuga' => array(array('method' => 'fuga', 'action' => 'piyo'),
                   array('method' => 'fuga_put', 'action' => 'piyo', 'accept' => array('PUT'))),
));
$rm = $_SERVER['REQUEST_METHOD'];
$_SERVER['REQUEST_METHOD'] = 'PUT';
$dispatched = dispatch('/fuga');
$t->is($dispatched['action'], 'piyo', '"/fuga"のアクション名は"piyo"');
$t->is($dispatched['method'], 'fuga_put', '"/fuga"のメソッド名は"fuga_put"');
$_SERVER['REQUEST_METHOD'] = 'GET';
$dispatched = dispatch('/fuga');
$t->is($dispatched['action'], 'piyo', '"/fuga"のアクション名は"piyo"');
$t->is($dispatched['method'], 'fuga', '"/fuga"のメソッド名は"fuga"');

register_dispatch('/xxx', array(
  '/hoge' => array('action' => 'hoge', 'method' => 'index')
));
$dispatched = dispatch('/xxx/hoge');
$t->is($dispatched['action'], 'hoge', '"/xxx/hoge"のアクション名は"hoge"');
$t->is($dispatched['method'], 'index', '"/xxx/hoge"のメソッド名は"index"');

$t->diag('function url_for test');
$t->is(url_for('/hoge'), '/hoge', 'returns /hoge');
$t->is(url_for('/hoge', array('foo' => 'bar', 'baz' => 'yah')), '/hoge?foo=bar&baz=yah', 'returns /hoge?foo=bar&baz=yah');

$t->diag('validation test');
$t->ok(!Pikotan_validate_not_null(''), '"Pikotan_validate_not_null" では空文字がエラー');
$t->ok(Pikotan_validate_not_null('1'), '"Pikotan_validate_not_null" では空文字でなければ正常');
$t->ok(Pikotan_validate_not_null(0), '"Pikotan_validate_not_null" では0は正常');
$t->ok(Pikotan_validate_ascii('abc'), '"Pikotan_validate_ascii" では "abc" は正常');
$t->ok(!Pikotan_validate_ascii('abcあ'), '"Pikotan_validate_ascii" では "abcあ" はエラー');
$t->ok(!Pikotan_validate_ascii('abc&&$G'), '"Pikotan_validate_ascii" では "abc&&$G" はエラー');
$t->ok(Pikotan_validate_numeric(''), '"Pikotan_validate_numeric" では空文字は正常');
$t->ok(!Pikotan_validate_numeric('a'), '"Pikotan_validate_numeric" では "a" はエラー');
$t->ok(Pikotan_validate_numeric('11'), '"Pikotan_validate_numeric" では "11" は正常');
$t->ok(!Pikotan_validate_numeric('11a'), '"Pikotan_validate_numeric" では "11a" はエラー');
$t->ok(Pikotan_validate_length('aa', 0, 10), '"Pikotan_validate_length" では "\'aa\', 0, 10" は正常');
$t->ok(Pikotan_validate_length('aaaaa', 0, 5), '"Pikotan_validate_length" では "\'aaaaa\', 0, 5" は正常');
$t->ok(!Pikotan_validate_length('aaaaa', 0, 3), '"Pikotan_validate_length" では "\'aaaaa\', 0, 3" はエラー');
$t->ok(Pikotan_validate_length('あああ', 0, 3), '"Pikotan_validate_length" では "\'あああ\', 0, 3" は正常');
$t->ok(!Pikotan_validate_length('aa', 3, 5), '"Pikotan_validate_length" では "\'aa\', 3, 5" はエラー');
$t->ok(Pikotan_validate_regex('aaa', '/^a+?$/'), '"Pikotan_validate_regex" では "\'aaa\', \'/^a+?$/\'" は正常');
$t->ok(!Pikotan_validate_regex('bbb', '/^a+?$/'), '"Pikotan_validate_regex" では "\'bbb\', \'/^a+?$/\'" はエラー');
$t->ok(Pikotan_validate_in_list('hoge', array('hoge', 'fuga', 'piyo')), '"Pikotan_validate_in_list" では "\'hoge\', array(\'hoge\', \'fuga\', \'piyo\')" は正常');
$t->ok(!Pikotan_validate_in_list('hogi', array('hoge', 'fuga', 'piyo')), '"Pikotan_validate_in_list" では "\'hogi\', array(\'hoge\', \'fuga\', \'piyo\')" はエラー');

$t->diag('function validate test');
$cond = array(
  'hoge' => array(
    'constraints' => array('not_null', 'numeric'),
    'messages' => array(
      'not_null' => 'not null test',
      'numeric'  => 'numeric test',
    )
  )
);
$_REQUEST['hoge'] = '';
$t->is(validate($cond), false, '空文字での "hoge" のバリデーションエラー');
$t->is(get_error('hoge'), 'not null test', '"not null test" のメッセージが返却される');
$errors = array();
$_REQUEST['hoge'] = 'abc';
$t->is(validate($cond), false, '"abc" といれた時の "hoge" のバリデーションエラー');
$t->is(get_error('hoge'), 'numeric test', '"numeric test" が返却される');
$pikotan_errors = array();
$_REQUEST['hoge'] = 5;
$t->is(validate($cond), true, 'valid "hoge"');

$t->diag("function load_lib test");
$t->ok(!load_lib("aaaaaaaaaaaaa"), '"aaaaaaaaaaaaa.php" なんてライブラリは検索できるパス上には存在しない');
$t->ok(!function_exists('myveryverysimpletest'), '"myveryverysimpletest" 関数は存在しない');
$t->ok(load_lib("t"), '"t.php" が見つかった');
$t->ok(function_exists('myveryverysimpletest'), 't.phpがロードされたので、 "myveryverysimpletest" 関数がロードされた');

$t->diag("function app_config test");
$conf = app_config();
$t->ok(empty($conf), 'app_configには何も指定してないので空');
$conf = app_config('hoge');
$t->ok(is_null($conf), 'hogeパラメータはまだないのでnullが返る');
app_config(array('hoge' => 'fuga'));
$hoge = app_config('hoge');
$t->is($hoge, 'fuga', 'app_configにhogeパラメータを加えたので返る');
$conf = app_config(array('foo' => 'bar'));
$t->is($conf, array('hoge' => 'fuga', 'foo' => 'bar'), '配列はマージされる');

$t->diag('registering model test');
$t->ok(!models('hoge'), 'hogeというキーでmodelは登録されていないのでfalseが返る');
function _registermodel_hoge1()
{
  return array('hoge' => 'fuga');
}
register_model('hoge', '_registermodel_hoge1');
$h = models('hoge');
$t->ok($h, 'hogeというキーでmodelが登録されたので、そのコンストラクタが走った返り値が格納され、返却される');
$t->is($h, array('hoge' => 'fuga'), 'hoge modelに格納されているのは "array(\'hoge\' => \'fuga\')"');
$hoge2_counter = 100;
function _registermodel_hoge2()
{
  global $hoge2_counter;
  $hoge2_counter++;
  return $hoge2_counter;
}
register_model('hoge2', '_registermodel_hoge2');
$h2 = models('hoge2');
$t->is($h2, 101, 'hoge2 modelには101が格納されている');
