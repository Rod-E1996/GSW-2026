<div class="card">
	<div class="card-body pt-3">
		<div class="form-group  {{ $errors->has('name') ? 'has-error' : ''}}">
			<label>Nombre:</label>
			<input type="text" name="name" class="form-control"
			value="{{  old('name') ?? $usuario->name ?? '' }}" required>
			{!! $errors->first('name', '<p class="text-danger">:message</p>') !!}
		</div>

		<div class="form-group {{ $errors->has('email') ? 'has-error' : ''}}">
			<label>Email:</label>
			<input type="text" name="email" class="form-control" value="{{ old('email') ?? $usuario->email ?? '' }}" required>
			{!! $errors->first('email', '<p class="text-danger">:message</p>') !!}
		</div>

		<div class="form-group  {{ $errors->has('id_rol') ? 'has-error' : ''}}">
			<label>
				Rol:
				<!-- Button trigger modal -->
				<button type="button" class="btn px-1 py-0" data-bs-toggle="modal"
                    title="Descripción de roles" data-bs-target="#exampleModal">
					<i class="fas fa-question-circle"></i>
				</button>
			</label>
			<select class="form-control select2" name="id_rol[]" id="id_rol" style="width: 100%;" multiple="multiple">
				@foreach($roles as $value)
					<option value="{{ $value->name }}">{{ $value->name }}</option>
				@endforeach
			</select>
			{!! $errors->first('id_rol', '<p class="text-danger">:message</p>') !!}
		</div>

		<div class="row">
			<div class="form-group col-md-6 col-sm-12 {{ $errors->has('password') ? 'has-error' : ''}}">
				<label>Contraseña:</label>
				<input type="password" name="password" class="form-control">
				{!! $errors->first('password', '<p class="text-danger">:message</p>') !!}
			</div>
			<div class="form-group col-md-6 col-sm-12">
				<label>Repetir Contraseña:</label>
				<input type="password" name="password_confirmation" class="form-control">
			</div>
		</div>
	</div>
	<div class="card-footer">
		<div class="form-group">
		    <a href="{{ url('usuario') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
		        <i class="fas fa-arrow-left"></i> &nbsp;Atrás
		    </a>
		    <button class="btn btn-primary" type="submit" data-bs-toggle="tooltip" title="{{ $formMode === 'Editar' ? 'Editar' : 'Crear' }}">
		    	<i class="fas fa-save"></i>&nbsp;{{ $formMode === 'Editar' ? 'Editar' : 'Crear' }}
		    </button>
		</div>
	</div>
</div>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog">
	  <div class="modal-content">
		<div class="modal-header">
		  <h5 class="modal-title" id="exampleModalLabel">Descripción de roles</h5>
		  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
		</div>
		<div class="modal-body">
		  <ul>
			  @foreach ($roles as $value)
				  <li><b>{{ $value->name }}: </b> {{ $value->guard_name }}</li>
			  @endforeach
		  </ul>
		</div>
		<div class="modal-footer">
		  <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
		</div>
	  </div>
	</div>
  </div>

<script type="text/javascript">
	$(document).ready(function() {
		var roles = {!! isset($usuario)? json_encode($usuario->getRoleNames()) : json_encode([]);  !!};

		$.each(roles, function(index, val) {
			$("#id_rol option[value='"+val+"']").attr('selected', true);
		});

        $('.select2').select2({ theme: 'bootstrap4', width: '100%', dropdownAutoWidth: true });

        $(document).on('select2:open', () => {
            document.querySelector('.select2-search__field').focus();
        });

	});
</script>
