<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Iniciar sesión · UrbanTwin</title>
</head>
<body>
    <h1>UrbanTwin Perú</h1>
    <form method="POST" action="/login">
        @csrf
        <label>Correo
            <input type="email" name="email" value="{{ old('email') }}" required>
        </label>
        <label>Contraseña
            <input type="password" name="password" required>
        </label>
        @error('email') <p style="color:red">{{ $message }}</p> @enderror
        <button type="submit">Entrar</button>
    </form>
</body>
</html>