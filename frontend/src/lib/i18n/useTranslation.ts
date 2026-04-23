import { useLocaleStore } from './store';
import { messages, type TranslationKey } from './messages';

/**
 * 翻訳文字列取得 hook。
 *
 *   const t = useTranslation();
 *   t('nav.members')                  // "メンバー"
 *   t('kpi.projectsCount', { count: 3 }) // "3 プロジェクト"
 *
 * 補間は {key} 形式で 1:1 置換。複数形 / 性別等の高度な変換は未対応。
 */
export function useTranslation(): (key: TranslationKey, vars?: Record<string, string | number>) => string {
  const locale = useLocaleStore((s) => s.locale);
  const dict = messages[locale];

  return (key, vars) => {
    const template = dict[key] ?? key;
    if (!vars) return template;
    return Object.entries(vars).reduce<string>(
      (acc, [k, v]) => acc.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v)),
      template,
    );
  };
}
