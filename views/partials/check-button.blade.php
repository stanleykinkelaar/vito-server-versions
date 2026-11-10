<form method="POST" action="{{ url('/servers/'.$server->id.'/features/server-versions/check') }}" data-check>
    @csrf
    <button type="submit" class="button {{ $class ?? 'primary' }}">{{ $label ?? 'Check now' }}</button>
</form>
