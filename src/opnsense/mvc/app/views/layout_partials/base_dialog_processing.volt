<!-- Static Modal "Processing request", prevent user input while busy -->
<div class="modal modal-static fade" id="processing-dialog" role="dialog" aria-hidden="true">
    <div class="modal-backdrop fade in"></div>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="text-center">
                    <i class="fa fa-spinner fa-pulse fa-5x"></i>
                    <h4>{{ lang._('Processing request...') }} </h4>
                </div>
            </div>
        </div>
    </div>
</div>
