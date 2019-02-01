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
  
  <div class="navbar">
    <div class="navbar-inner">
      <div class="container">
        <ul class="nav">
        @if (Auth::check())
          <li>{{ HTML::link_to_action('home@logout', 'Çıkış') }}</li>
        @else
          <li>{{ HTML::link_to_action('home@login', 'Giriş') }}</li>
        @endif
        </ul>
		<ul class="nav pull-right">
        @if (Auth::check() AND Auth::user()->role === 'admin')
          <li>{{ HTML::link_to_action('user@index', 'Kullanıcı Yönetimi') }}</li>
        @endif
        </ul>		
      </div>
    </div>
  </div>
    
    <div class="content">
      @if (Session::has('message'))
      <div class="alert alert-{{ Session::get('message_status') }}">{{ Session::get('message') }}</div>
      @endif
      
      @_yield('content')
    </div>
  
  <footer>
    <p>© Mono Bilişim 2012</p>
  </footer>
  
  </div>
</body>
</html>