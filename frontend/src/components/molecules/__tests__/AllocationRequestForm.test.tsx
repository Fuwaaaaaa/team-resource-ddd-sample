import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AllocationRequestForm } from '../AllocationRequestForm';
import * as membersApi from '@/features/members/api';
import * as projectsApi from '@/features/projects/api';
import * as skillsApi from '@/features/skills/api';
import * as requestsApi from '@/features/allocationRequests/api';

jest.mock('@/features/members/api');
jest.mock('@/features/projects/api');
jest.mock('@/features/skills/api');
jest.mock('@/features/allocationRequests/api');

function mockLists(): void {
  jest.spyOn(membersApi, 'useMembers').mockReturnValue({
    data: [{ id: 'm1', name: 'Alice', standardHoursPerDay: 8 }],
  } as unknown as ReturnType<typeof membersApi.useMembers>);
  jest.spyOn(projectsApi, 'useProjects').mockReturnValue({
    data: [{ id: 'p1', name: 'Alpha', status: 'active' }],
  } as unknown as ReturnType<typeof projectsApi.useProjects>);
  jest.spyOn(skillsApi, 'useSkills').mockReturnValue({
    data: [{ id: 's1', name: 'PHP', category: 'programming_language' }],
  } as unknown as ReturnType<typeof skillsApi.useSkills>);
}

describe('AllocationRequestForm', () => {
  afterEach(() => jest.clearAllMocks());

  it('必須項目が揃うまで送信ボタンは disabled', async () => {
    mockLists();
    const mutateAsync = jest.fn();
    jest.spyOn(requestsApi, 'useSubmitAllocationRequest').mockReturnValue({
      mutateAsync,
      isPending: false,
    } as unknown as ReturnType<typeof requestsApi.useSubmitAllocationRequest>);

    render(<AllocationRequestForm />);
    const submitBtn = screen.getByRole('button', { name: /申請を提出/ });
    expect(submitBtn).toBeDisabled();
  });

  it('フォーム送信で正しいペイロードが渡る', async () => {
    mockLists();
    const mutateAsync = jest.fn().mockResolvedValue({
      id: '0198xxx-yyy',
      type: 'create_allocation',
      payload: {},
      requestedBy: 1,
      reason: null,
      status: 'pending',
      requestedAt: '2026-05-01T00:00:00Z',
      decidedBy: null,
      decidedAt: null,
      decisionNote: null,
      resultingAllocationId: null,
    });
    jest.spyOn(requestsApi, 'useSubmitAllocationRequest').mockReturnValue({
      mutateAsync,
      isPending: false,
    } as unknown as ReturnType<typeof requestsApi.useSubmitAllocationRequest>);

    const user = userEvent.setup();
    render(<AllocationRequestForm />);

    await user.selectOptions(screen.getByLabelText('Member'), 'm1');
    await user.selectOptions(screen.getByLabelText('Project'), 'p1');
    await user.selectOptions(screen.getByLabelText('Skill'), 's1');

    // 開始/終了日
    fireEvent.change(screen.getByLabelText('開始日'), { target: { value: '2026-05-01' } });
    fireEvent.change(screen.getByLabelText('終了日'), { target: { value: '2026-05-31' } });

    const submitBtn = screen.getByRole('button', { name: /申請を提出/ });
    expect(submitBtn).toBeEnabled();

    await user.click(submitBtn);

    await waitFor(() => expect(mutateAsync).toHaveBeenCalledTimes(1));
    expect(mutateAsync).toHaveBeenCalledWith({
      type: 'create_allocation',
      payload: {
        memberId: 'm1',
        projectId: 'p1',
        skillId: 's1',
        allocationPercentage: 50,
        periodStart: '2026-05-01',
        periodEnd: '2026-05-31',
      },
      reason: null,
    });
  });

  it('送信失敗時にエラーメッセージ表示', async () => {
    mockLists();
    const mutateAsync = jest.fn().mockRejectedValue(new Error('Capacity exceeded'));
    jest.spyOn(requestsApi, 'useSubmitAllocationRequest').mockReturnValue({
      mutateAsync,
      isPending: false,
    } as unknown as ReturnType<typeof requestsApi.useSubmitAllocationRequest>);

    const user = userEvent.setup();
    render(<AllocationRequestForm />);
    await user.selectOptions(screen.getByLabelText('Member'), 'm1');
    await user.selectOptions(screen.getByLabelText('Project'), 'p1');
    await user.selectOptions(screen.getByLabelText('Skill'), 's1');
    fireEvent.change(screen.getByLabelText('開始日'), { target: { value: '2026-05-01' } });
    fireEvent.change(screen.getByLabelText('終了日'), { target: { value: '2026-05-31' } });

    await user.click(screen.getByRole('button', { name: /申請を提出/ }));

    await waitFor(() => {
      expect(screen.getByText(/Capacity exceeded/)).toBeInTheDocument();
    });
  });
});
