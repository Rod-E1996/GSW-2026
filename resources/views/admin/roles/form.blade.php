<div class="card">
	<div class="card-body pt-3">

        <div class="input-group">

			<div class="col-md-12 col-12 p-1">
				<div class="form-group {{ $errors->has('name') ? 'has-error' : ''}}">
					<label>Nombre: <span class="text-danger">*</span></label>
					<input type="text" name="name" class="form-control"
						value="{{  old('name') ?? $role->name ?? '' }}">
					{!! $errors->first('name', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

	</div>
	<div class="card-footer">
		<div class="form-group">
		    <a href="{{ url('role') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
		        <i class="fas fa-arrow-left"></i> &nbsp;Atrás
		    </a>
		    <button class="btn btn-primary" type="submit" data-bs-toggle="tooltip" title="{{ $formMode === 'Editar' ? 'Editar' : 'Crear' }}">
		    	<i class="fas fa-save"></i>&nbsp;{{ $formMode === 'Editar' ? 'Editar' : 'Crear' }}
		    </button>
		</div>
	</div>
</div>
