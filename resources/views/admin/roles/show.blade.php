@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Detalles del rol: '. $role->name }}
@endsection

@section('content')

    <div class="container">
        <div class="card">
            <div class="card-header">
                <a href="{{ url('role') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
                    <i class="fas fa-arrow-left"></i>&nbsp;Atrás
                </a>
                <a href="{{ url('role/' . $role->id . '/edit ') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Editar">
                    <i class="fas fa-edit"></i> &nbsp;Editar
                </a>
            </div>

            <div class="card-body">
                <table class="table table-bordered text-center">
                    <tbody>
                        <tr>
                            <th>NOMBRE</th>
                            <td>{{ $role->name }}</td>
                        </tr>
                        <tr>
                            <th>FECHA</th>
                            <td>{{ \Carbon\Carbon::parse($role->created_at)->format('Y-m-d') ?? '' }}</td>
                        </tr>
                        <tr>
                            <th>HORA</th>
                            <td>{{ \Carbon\Carbon::parse($role->created_at)->format('h:i:s A') ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
