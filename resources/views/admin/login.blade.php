@extends('layouts.login')

@section('content')
<div class="limiter">
    <div class="container-login100">
        <div class="wrap-login100">
            <form class="login100-form validate-form" method="POST" action="{{ route('admin.login') }}">
                @csrf

                <span class="login100-form-title d-block mb-2">
                    Jannatul Firdaus
                </span>
                <span class="login100-form-title grade-badge mb-4">Grade 6</span>

                @if($errors->any())
                    <div class="alert alert-danger py-2 small mb-3">{{ $errors->first() }}</div>
                @endif

                <div class="wrap-input100 validate-input">
                    <input class="input100" type="text" name="username" value="{{ old('username') }}" autofocus>
                    <span class="focus-input100" data-placeholder="Username"></span>
                </div>

                <div class="wrap-input100 validate-input">
                    <span class="btn-show-pass">
                        <i class="bi bi-eye"></i>
                    </span>
                    <input class="input100" type="password" name="password">
                    <span class="focus-input100" data-placeholder="Password"></span>
                </div>

                <div class="container-login100-form-btn">
                    <div class="wrap-login100-form-btn">
                        <div class="login100-form-bgbtn"></div>
                        <button class="login100-form-btn">
                            Login
                        </button>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a class="txt2" href="{{ route('home') }}">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Beranda
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.input100').forEach(function (input) {
            input.addEventListener('input', function () {
                input.classList.toggle('has-val', input.value.trim() !== '');
            });
        });

        var pass = document.querySelector('.btn-show-pass');
        if (pass) {
            pass.addEventListener('click', function () {
                var input = this.parentElement.querySelector('.input100');
                var icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        }
    });
</script>
@endsection
