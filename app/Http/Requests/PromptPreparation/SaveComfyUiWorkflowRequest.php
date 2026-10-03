<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class SaveComfyUiWorkflowRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'workflow' => ['required', 'file', 'max:5120'],
            'positiveNodeId' => ['required', 'string', 'max:255'],
            'positiveInputName' => ['required', 'string', 'max:255'],
            'negativeNodeId' => ['required', 'string', 'max:255'],
            'negativeInputName' => ['required', 'string', 'max:255'],
            'seedNodeId' => ['required', 'string', 'max:255'],
            'seedInputName' => ['required', 'string', 'max:255'],
            'checkpointMappings' => ['required', 'array', 'min:1'],
            'checkpointMappings.*.nodeId' => ['required', 'string', 'max:255'],
            'checkpointMappings.*.inputName' => ['required', 'string', 'max:255'],
            'samplerMappings' => ['required', 'array', 'min:1'],
            'samplerMappings.*.nodeId' => ['required', 'string', 'max:255'],
            'samplerMappings.*.inputName' => ['required', 'string', 'max:255'],
            'schedulerMappings' => ['required', 'array', 'min:1'],
            'schedulerMappings.*.nodeId' => ['required', 'string', 'max:255'],
            'schedulerMappings.*.inputName' => ['required', 'string', 'max:255'],
            'outputFilenamePrefixMappings' => ['sometimes', 'array', 'max:1'],
            'outputFilenamePrefixMappings.*.nodeId' => ['required', 'string', 'max:255'],
            'outputFilenamePrefixMappings.*.inputName' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'checkpointMappings.required' => 'checkpointの入力位置を一行以上設定してください。',
            'checkpointMappings.min' => 'checkpointの入力位置を一行以上設定してください。',
            'checkpointMappings.*.nodeId.required' => 'checkpointのノードIDを入力してください。',
            'checkpointMappings.*.inputName.required' => 'checkpointの入力名を入力してください。',
            'samplerMappings.required' => 'サンプラーの入力位置を一行以上設定してください。',
            'samplerMappings.min' => 'サンプラーの入力位置を一行以上設定してください。',
            'samplerMappings.*.nodeId.required' => 'サンプラーのノードIDを入力してください。',
            'samplerMappings.*.inputName.required' => 'サンプラーの入力名を入力してください。',
            'schedulerMappings.required' => 'スケジューラの入力位置を一行以上設定してください。',
            'schedulerMappings.min' => 'スケジューラの入力位置を一行以上設定してください。',
            'schedulerMappings.*.nodeId.required' => 'スケジューラのノードIDを入力してください。',
            'schedulerMappings.*.inputName.required' => 'スケジューラの入力名を入力してください。',
            'outputFilenamePrefixMappings.max' => '保存先プレフィックスの入力位置は一行だけ設定できます。',
            'outputFilenamePrefixMappings.*.nodeId.required' => '保存先プレフィックスのノードIDを入力してください。',
            'outputFilenamePrefixMappings.*.inputName.required' => '保存先プレフィックスの入力名を入力してください。',
        ];
    }
}
