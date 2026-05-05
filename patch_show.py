import sys

file_path = "f:/xampp/htdocs/tms/resources/views/change_request/show.blade.php"
with open(file_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

new_lines = []
for i, line in enumerate(lines):
    line_num = i + 1
    
    # 1. Add styles
    if line_num == 4:
        new_lines.append(line)
        new_lines.append("    @include('change_request.partials.styles')\n")
        continue

    # 2. Main replacement
    if line_num == 104:
        new_lines.append("""                            <div class="card-body" @if($isCurrentDeploymentPreReq && $deploymentApprovalValue !== '1') style="pointer-events: none; opacity: 0.5;" @endif>
                                <fieldset disabled>
                                    <div class="row">
                                        @include("$view.custom_fields")
                                    </div>
                                    @if($cr->current_status->new_status_id == 113)
                                        @if(isset($man_day) && count($man_day) > 0)
                                            @php
                                                $manDayText = '';
                                                foreach ($man_day as $item) {
                                                    $manDayText .= $item['custom_field_value'] . ' ';
                                                }
                                                $manDayText = trim($manDayText);
                                            @endphp

                                            <p><label class="form-control-lg">MD's</label> => {{ $manDayText }}</p>
                                        @endif
                                    @endif
                                </fieldset>
                            </div>

                            <!-- start feedback table -->
                            @include('change_request.partials.feedback_section')
                            <!-- end feedback table -->

                            @include('change_request.partials.attachments_section')

""")
        continue
    if 104 < line_num <= 346:
        continue # Skip these lines

    # 3. Add defects section before cr_logs
    if line_num == 408:
        new_lines.append('                        @include(\'change_request.partials.defects_section\')\n\n')
        new_lines.append(line)
        continue

    # 4. Add scripts included before @endsection
    if line_num == 425:
        new_lines.append(line)
        new_lines.append('\n')
        new_lines.append('    @include(\'change_request.partials.scripts\')\n')
        continue

    new_lines.append(line)

with open(file_path, "w", encoding="utf-8") as f:
    f.writelines(new_lines)

print("Done")
