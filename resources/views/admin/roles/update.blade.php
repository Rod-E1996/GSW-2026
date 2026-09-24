@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Datos del rol' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('role/' . $role->id ) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include ('admin.roles.form', ['formMode' => 'Editar'])
        </form>
    <div>

    <div class="container">
        <div class="card p-4">

            <div class="form-group">
                <select multiple="multiple" id="dualList"></select>
            </div>

        </div>
    </div>

    <script>
        $(document).ready(function() {

            var id_rol ={{$role->id }}
            var columna1 =  {!! json_encode($columna1) !!};
            var columna2 =  {!! json_encode($columna2) !!};

            $.each(columna1, function(i, item) {
                var option = $('<option>', {
                    value: item.value,
                    text: item.text
                });
                $('#dualList').append(option);
            });

            $.each(columna2, function(i, item) {
                var option = $('<option>', {
                    value: item.value,
                    text: item.text
                });
                $('#dualList').append(option);
                option.prop('selected', true); // seleccionar los elementos de la columna 2
            });

            $('#dualList').bootstrapDualListbox({
                nonSelectedListLabel: 'Permisos no asignados',
                selectedListLabel: 'Permisos asignados',
                preserveSelectionOnMove: 'moved',
                moveOnSelect: false,
                removeOnDeselect: false,
            });

            $('#dualList').on('change', function() {
                var selectedItems = $('#dualList').val();
                var url = "{{ route('role_move_permiso') }}";

                $.ajax({
                    type: "POST",
                    url: url,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { items: selectedItems, id_rol: id_rol },
                    success: function(response) {

                        if(response.success){
                            toastr.success(response.text)
                        }else{
                            toastr.error(response.text)
                            setTimeout(function(){
                                location.reload();
                            }, 2000);
                        }

                    },
                    error: function(response) {
                        toastr.error('Ha ocurrido un error')
                    }
                });

            });

        });
    </script>

@endsection
