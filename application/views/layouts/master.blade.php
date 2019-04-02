<!DOCTYPE html>
<html lang="en">
<head>    
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow" />
    <title>Çağrı Kayıtları</title>
    {{ Asset::styles() }}
    {{ Asset::scripts() }}
</head>
<body>
    <div class="container">
    
    <h2>{{ HTML::link('cdr', 'Çağrı Kayıtları') }}</a></h2>
    
    @if (Auth::check())
    <div class="navbar">
        <div class="navbar-inner">
            <div class="container">
                <ul class="nav">
                @if (Config::get('application.call_tags'))
                    <li>{{ HTML::link('tag', 'Etiket Raporu') }}</li>
                @endif
                @if (Auth::check() AND Auth::user()->role === 'admin')
                    <li>{{ HTML::link_to_action('user@index', 'Kullanıcı Yönetimi') }}</li>
                @endif
                </ul>
                <ul class="nav pull-right">
                    <li>{{ HTML::link_to_action('home@logout', 'Çıkış') }}</li>
                </ul>
            </div>
        </div>
    </div>
    @endif
        
    <div class="content">
        @if (Session::has('message'))
        <div class="alert alert-{{ Session::get('message_status') }}">{{ Session::get('message') }}</div>
        @endif
        
        @_yield('content')
    </div>
    
    <footer>
        <p>© 2012 - 2019 Mono Bilişim</p>
    </footer>
    
    </div>
</body>
</html>
