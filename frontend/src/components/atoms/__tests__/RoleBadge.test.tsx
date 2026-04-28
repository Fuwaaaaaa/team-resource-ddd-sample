import { render, screen } from '@testing-library/react';
import { RoleBadge } from '../RoleBadge';

describe('RoleBadge', () => {
  it('renders the role text and an aria-label for screen readers', () => {
    render(<RoleBadge role="admin" />);
    const el = screen.getByLabelText('role: admin');
    expect(el).toBeInTheDocument();
    expect(el).toHaveTextContent('admin');
  });

  it('admin variant uses the red token (light + dark)', () => {
    render(<RoleBadge role="admin" />);
    const el = screen.getByLabelText('role: admin');
    expect(el.className).toContain('bg-red-50');
    expect(el.className).toContain('dark:bg-red-900/30');
  });

  it('manager variant uses the blue token', () => {
    render(<RoleBadge role="manager" />);
    const el = screen.getByLabelText('role: manager');
    expect(el.className).toContain('bg-blue-50');
    expect(el.className).toContain('dark:bg-blue-900/30');
  });

  it('viewer variant uses the muted surface token', () => {
    render(<RoleBadge role="viewer" />);
    const el = screen.getByLabelText('role: viewer');
    expect(el.className).toContain('bg-surface-muted');
  });
});
