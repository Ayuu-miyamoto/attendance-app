# 提供Bladeテンプレート — 勤怠管理アプリ

## このリポジトリについて

本リポジトリは、模擬案件「勤怠管理アプリ」で使用する **提供済みBladeテンプレートおよびフロントエンドリソース（CSS / JS）** を配布するためのリポジトリです。

受講生は、本リポジトリの `resources/` と `public/images/` を自分のLaravelプロジェクトに移入して使用します。

> **注意:** このリポジトリ単体ではアプリケーションとして動作しません。受講生が作成するLaravelプロジェクトに `resources/` を移入して初めて機能します。

## ブランチ構成

| ブランチ | 用途 | 移入タイミング |
|---|---|---|
| `basic` | 基本機能用テンプレート | 基本機能の実装開始時 |
| `advanced` | 応用機能用テンプレート | 基本機能完了後、応用機能の着手時 |

### basic → advanced での主な変更点

- **追加**: `resources/views/reports/index.blade.php`（マイ勤怠レポート画面）
- **追加**: `resources/css/reports/index.css`（マイ勤怠レポート画面のスタイル）

## 使い方

### Step 1: 基本機能の実装開始時（basic ブランチ）

#### 1. リポジトリをクローン

ターミナルで、自分のLaravelプロジェクトとは **別の場所** に以下を実行します。

```bash
git clone -b basic https://github.com/coachtech-prepared-file/Preparedblade-mockcase-Attendance.git
```

#### 2. Finder（またはエクスプローラー）でフォルダを開く

```bash
open Preparedblade-mockcase-Attendance   # macOS の場合
```

Windows の場合はエクスプローラーでクローン先のフォルダを開いてください。

#### 3. resources/ と public/images/ を自分のプロジェクトに移入

クローンしたフォルダ内の `resources/` を、自分のLaravelプロジェクトの `resources/` に **上書き** でコピー（またはドラッグ＆ドロップ）してください。

続けて、クローンしたフォルダ内の `public/images/` を、自分のLaravelプロジェクトの `public/` に **上書き** でコピーしてください。ヘッダーのロゴ（`logo.svg`）と、勤怠一覧画面で使用するアイコン（`arrow.png` / `calender.png`）が含まれます。

#### 4. クローンしたフォルダの削除

移入が完了したら、クローンした `Preparedblade-mockcase-Attendance` フォルダは不要です。
Finder（またはエクスプローラー）上でゴミ箱に移動してください。

### Step 2: 応用機能の着手時（advanced ブランチ）

基本機能の実装が完了し応用機能に着手する際に、**advanced ブランチ** から `resources/` を再取得します。
手順は Step 1 と同様ですが、クローン時のブランチを `advanced` に変更してください。

## 補足

- 本テンプレートの CSS は手書きCSS（Tailwind は使用していません）で、`resources/css/` 配下に配置され **Vite** でビルドされます。
- フロントエンドの環境構築（Vite の設定・`vite.config.js` 等）は、**要件シートの「環境構築手順」** を参照してください。
