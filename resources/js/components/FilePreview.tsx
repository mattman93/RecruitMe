import { FileText, Image as ImageIcon } from "lucide-react";
import { Card } from "./ui/card";

interface FilePreviewProps {
  file?: File;
  preview?: string;
  // For uploaded files (dashboard)
  fileUrl?: string;
  fileName?: string;
  fileType?: string;
  fileSize?: number;
  uploadedAt?: string;
}

export function FilePreview({ file, preview, fileUrl, fileName, fileType, fileSize, uploadedAt }: FilePreviewProps) {
  // Determine if we're showing a File object or uploaded file data
  const isFileObject = !!file;
  const displayName = isFileObject ? file!.name : fileName || 'Unknown file';
  const displayType = isFileObject ? file!.type : fileType || '';
  const displaySize = isFileObject ? file!.size : fileSize || 0;
  const displayModified = isFileObject ? new Date(file!.lastModified).toLocaleDateString() : uploadedAt || 'Unknown';
  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getFileTypeDisplay = (mimeType: string) => {
    const typeMap: { [key: string]: string } = {
      'application/pdf': 'PDF',
      'application/msword': 'DOC',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
      'image/jpeg': 'JPEG',
      'image/png': 'PNG',
      'image/gif': 'GIF',
      'image/webp': 'WebP'
    };
    return typeMap[mimeType] || 'Unknown';
  };

  return (
    <Card className="p-6 w-full">
      <div className="space-y-4 w-full">
        {/* File Header */}
        <div className="border-b border-border pb-4">
          <h3 className="font-semibold file-preview-text mb-2">File Preview</h3>
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-lg">
              {displayType.startsWith('image/') ? (
                <ImageIcon className="h-6 w-6 text-primary" />
              ) : (
                <FileText className="h-6 w-6 text-primary" />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-medium file-preview-text truncate">
                {displayName}
              </p>
              <div className="flex items-center gap-2 text-sm file-preview-text">
                <span>{formatFileSize(displaySize)}</span>
                <span>•</span>
                <span>{getFileTypeDisplay(displayType)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Preview Content */}
        <div className="min-h-[300px] flex items-center justify-center">
          {preview ? (
            // Image preview
            <div className="w-full">
              <img
                src={preview}
                alt={displayName}
                className="max-w-full max-h-[400px] object-contain rounded-lg shadow-sm mx-auto"
              />
            </div>
          ) : fileUrl && displayType.startsWith('image/') ? (
            // Uploaded image preview
            <div className="w-full">
              <img
                src={fileUrl}
                alt={displayName}
                className="max-w-full max-h-[400px] object-contain rounded-lg shadow-sm mx-auto"
              />
            </div>
          ) : displayType === 'application/pdf' && (fileUrl || preview) ? (
            // PDF viewer
            <div className="w-full">
              <iframe
                src={fileUrl || preview}
                title={displayName}
                className="w-full h-[500px] rounded-lg border border-border"
                style={{ minHeight: '500px' }}
              />
              <p className="text-xs file-preview-text mt-2 text-center">
                If PDF doesn't display, try opening it in a new tab
              </p>
            </div>
          ) : displayType === 'application/pdf' ? (
            // PDF fallback when no URL available
            <div className="text-center space-y-4">
              <div className="p-6 bg-red-50 rounded-lg">
                <FileText className="h-16 w-16 text-red-600 mx-auto mb-4" />
                <p className="text-red-800 font-medium">PDF Document</p>
                <p className="text-red-600 text-sm">
                  PDF preview not available - no file URL provided
                </p>
              </div>
              <p className="text-sm file-preview-text">
                Click download to view the full PDF document
              </p>
            </div>
          ) : displayType.includes('document') || displayType.includes('word') ? (
            // Document placeholder
            <div className="text-center space-y-4">
              <div className="p-6 bg-blue-50 rounded-lg">
                <FileText className="h-16 w-16 text-blue-600 mx-auto mb-4" />
                <p className="text-blue-800 font-medium">
                  {getFileTypeDisplay(displayType)} Document
                </p>
                <p className="text-blue-600 text-sm">
                  Document preview not available
                </p>
              </div>
              <p className="text-sm file-preview-text">
                Download the file to view its contents
              </p>
            </div>
          ) : (
            // Generic file placeholder
            <div className="text-center space-y-4">
              <div className="p-6 bg-gray-50 rounded-lg">
                <FileText className="h-16 w-16 file-preview-text mx-auto mb-4" />
                <p className="file-preview-text font-medium">File Preview</p>
                <p className="file-preview-text text-sm">
                  Preview not available for this file type
                </p>
              </div>
            </div>
          )}
        </div>

        {/* File Details */}
        <div className="border-t border-border pt-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="file-preview-text">File Type</p>
              <p className="font-medium file-preview-text">
                {getFileTypeDisplay(displayType)}
              </p>
            </div>
            <div>
              <p className="file-preview-text">File Size</p>
              <p className="font-medium file-preview-text">
                {formatFileSize(displaySize)}
              </p>
            </div>
            <div>
              <p className="file-preview-text">{isFileObject ? 'Last Modified' : 'Uploaded'}</p>
              <p className="font-medium file-preview-text">
                {displayModified}
              </p>
            </div>
            <div>
              <p className="file-preview-text">MIME Type</p>
              <p className="font-medium file-preview-text text-xs">
                {displayType}
              </p>
            </div>
          </div>
        </div>
      </div>
    </Card>
  );
}